<?php
/**
 * عميل MazBot API — ردما للبوابات الإلكترونية
 *
 * التوثيق الرسمي: https://api.mazbot.net/
 *  - المصادقة مزدوجة: Header "apikey" مع كل طلب + JWT (Authorization: Bearer)
 *  - POST /login                    → الحصول على JWT (صلاحيته ~60 دقيقة، لا يوجد refresh)
 *  - POST /contact/resolve-by-phone → تحويل رقم الهاتف إلى receiver_id (ينشئ جهة الاتصال إن لم توجد)
 *  - POST /send-message             → رسالة نصية عادية (تتطلب receiver_id)
 *  - POST /whatsapp/send-template   → رسالة قالب معتمد (تتطلب template_id + mobile)
 */

declare(strict_types=1);

/**
 * طلب HTTP إلى MazBot عبر cURL مع مهلات صارمة.
 *
 * $asMultipart=true يرسل الحقول كـ multipart/form-data بدل JSON — مطلوب فعليًا
 * (تم التحقق تجريبيًا) لنقاط /send-message و /whatsapp/send-template حتى
 * للرسائل النصية البحتة، وليس فقط عند إرفاق ملفات كما أوحى التوثيق.
 *
 * @return array{status:int, body:array|null, error:string|null}
 */
function mazbotHttpPost(array $config, string $path, array $payload, ?string $jwt = null, bool $asMultipart = false): array
{
    $headers = [
        'apikey: ' . $config['api_key'],
        'Accept: application/json',
    ];
    if ($jwt !== null) {
        $headers[] = 'Authorization: Bearer ' . $jwt;
    }

    if ($asMultipart) {
        // المصفوفات يجب أن تُرسل كحقول مصفوفة فعلية بصيغة field[key]
        // (مثل body_values[1]، body_matchs[1]) وليس كنص JSON مُجمّع في حقل واحد.
        $postFields = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $postFields["{$key}[{$subKey}]"] = (string) $subValue;
                }
            } else {
                $postFields[$key] = (string) $value;
            }
        }
    } else {
        $headers[] = 'Content-Type: application/json';
        $postFields = json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    $ch = curl_init($config['base_url'] . $path);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $config['connect_timeout'],
        CURLOPT_TIMEOUT => $config['request_timeout'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $rawBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($rawBody === false) {
        return ['status' => 0, 'body' => null, 'error' => 'curl: ' . $curlError];
    }

    $decoded = json_decode((string) $rawBody, true);
    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : null,
        'error' => null,
    ];
}

/**
 * الحصول على JWT صالح — من الكاش إن أمكن، وإلا عبر تسجيل دخول جديد.
 * $forceRefresh = true يتجاوز الكاش (يُستخدم بعد استجابة 401).
 *
 * @return array{token:?string, error:?string}
 */
function getMazbotToken(array $config, bool $forceRefresh = false): array
{
    $cacheFile = __DIR__ . '/cache/mazbot-token.json';

    if (!$forceRefresh && is_readable($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)
            && !empty($cached['token'])
            && !empty($cached['expires_at'])
            && time() < (int) $cached['expires_at']
        ) {
            return ['token' => (string) $cached['token'], 'error' => null];
        }
    }

    $response = mazbotHttpPost($config, '/login', [
        'email' => $config['staff_email'],
        'password' => $config['staff_password'],
    ]);

    if ($response['error'] !== null) {
        return ['token' => null, 'error' => $response['error']];
    }

    $token = $response['body']['data']['token'] ?? null;
    if ($response['status'] !== 200 || !is_string($token) || $token === '') {
        return ['token' => null, 'error' => 'login_failed_http_' . $response['status']];
    }

    @file_put_contents($cacheFile, json_encode([
        'token' => $token,
        'expires_at' => time() + (int) $config['token_cache_ttl'],
    ]), LOCK_EX);

    return ['token' => $token, 'error' => null];
}

/**
 * تحويل رقم هاتف دولي (مثل 96890660001) إلى receiver_id.
 *
 * @return array{receiverId:?int, error:?string, status:int}
 */
function resolveMazbotContactId(array $config, string $jwt, string $phone): array
{
    $response = mazbotHttpPost($config, '/contact/resolve-by-phone', ['phone' => $phone], $jwt);

    if ($response['error'] !== null) {
        return ['receiverId' => null, 'error' => $response['error'], 'status' => $response['status']];
    }

    $body = $response['body'] ?? [];
    // التوثيق يذكر إرجاع receiver_id — نتحقق من أكثر من مسار محتمل في الاستجابة
    $receiverId = $body['data']['receiver_id']
        ?? $body['receiver_id']
        ?? $body['data']['id']
        ?? null;

    if ($response['status'] !== 200 || $receiverId === null) {
        return ['receiverId' => null, 'error' => 'resolve_failed_http_' . $response['status'], 'status' => $response['status']];
    }

    return ['receiverId' => (int) $receiverId, 'error' => null, 'status' => $response['status']];
}

/**
 * إرسال رسالة نصية عادية إلى receiver_id.
 *
 * @return array{success:bool, error:?string, status:int}
 */
function sendMazbotMessage(array $config, string $jwt, int $receiverId, string $text): array
{
    $response = mazbotHttpPost($config, '/send-message', [
        'receiver_id' => $receiverId,
        'message' => $text,
    ], $jwt, true);

    if ($response['error'] !== null) {
        return ['success' => false, 'error' => $response['error'], 'status' => $response['status']];
    }

    $ok = $response['status'] === 200 && !empty($response['body']['success']);
    return [
        'success' => $ok,
        'error' => $ok ? null : ('send_failed_http_' . $response['status'] . ' body=' . json_encode($response['body'], JSON_UNESCAPED_UNICODE)),
        'status' => $response['status'],
    ];
}

/**
 * إرسال رسالة قالب معتمد إلى رقم هاتف مباشرة.
 * $bodyValues: قيم متغيرات القالب مرتبة [1 => ..., 2 => ...].
 *
 * @return array{success:bool, error:?string, status:int}
 */
function sendMazbotTemplate(array $config, string $jwt, string $mobile, array $bodyValues = [], ?int $templateId = null): array
{
    $payload = [
        'template_id' => $templateId ?? (int) $config['template_id'],
        'mobile' => $mobile,
    ];
    if ($bodyValues !== []) {
        $matches = [];
        foreach (array_keys($bodyValues) as $index) {
            $matches[$index] = 'input_value';
        }
        $payload['body_matchs'] = $matches;
        $payload['body_values'] = $bodyValues;
    }

    $response = mazbotHttpPost($config, '/whatsapp/send-template', $payload, $jwt, true);

    if ($response['error'] !== null) {
        return ['success' => false, 'error' => $response['error'], 'status' => $response['status']];
    }

    $ok = $response['status'] === 200 && !empty($response['body']['success']);
    return [
        'success' => $ok,
        'error' => $ok ? null : ('template_failed_http_' . $response['status'] . ' body=' . json_encode($response['body'], JSON_UNESCAPED_UNICODE)),
        'status' => $response['status'],
    ];
}

/**
 * إرسال إشعار طلب عرض سعر إلى رقم واحد، مع معالجة كاملة:
 *  - dry_run: تسجيل فقط بدون اتصال فعلي
 *  - إعادة login واحدة عند 401 (انتهاء JWT)
 *  - إعادة محاولة واحدة فقط للأخطاء المؤقتة (timeout / 5xx)
 *
 * @return array{success:bool, error:?string, status:int}
 */
function sendQuoteToRecipient(array $config, string $recipient, string $messageText, array $templateValues = [], ?int $templateId = null): array
{
    if (!empty($config['dry_run'])) {
        return ['success' => true, 'error' => 'dry_run', 'status' => 200];
    }

    $tokenResult = getMazbotToken($config);
    if ($tokenResult['token'] === null) {
        return ['success' => false, 'error' => $tokenResult['error'], 'status' => 0];
    }
    $jwt = $tokenResult['token'];

    $attempt = function (string $jwt) use ($config, $recipient, $messageText, $templateValues, $templateId): array {
        if ($config['send_mode'] === 'template') {
            return sendMazbotTemplate($config, $jwt, $recipient, $templateValues, $templateId);
        }
        $resolved = resolveMazbotContactId($config, $jwt, $recipient);
        if ($resolved['receiverId'] === null) {
            return ['success' => false, 'error' => $resolved['error'], 'status' => $resolved['status']];
        }
        return sendMazbotMessage($config, $jwt, $resolved['receiverId'], $messageText);
    };

    $result = $attempt($jwt);

    // انتهاء صلاحية JWT → تسجيل دخول جديد ومحاولة واحدة إضافية
    if (!$result['success'] && $result['status'] === 401) {
        $tokenResult = getMazbotToken($config, true);
        if ($tokenResult['token'] !== null) {
            $result = $attempt($tokenResult['token']);
        }
    }

    // خطأ مؤقت (شبكة أو 5xx) → إعادة محاولة واحدة فقط
    if (!$result['success'] && ($result['status'] === 0 || $result['status'] >= 500)) {
        $result = $attempt($jwt);
    }

    return $result;
}
