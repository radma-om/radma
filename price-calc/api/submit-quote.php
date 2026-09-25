<?php
/**
 * Endpoint موحد لاستقبال طلبات عروض الأسعار — ردما للبوابات الإلكترونية
 *
 * يستقبل JSON من حاسبتي الرولينج شتر والأوفرهيد، يتحقق من البيانات،
 * يعيد حساب السعر Server-side، ثم يرسل الطلب عبر MazBot API إلى
 * أرقام الاستقبال المحددة في الإعدادات — كل رقم بشكل مستقل.
 *
 * الاستجابة للعميل عامة دائمًا: {"success": bool, "requestId": string|null}
 * (لا تُعرض أي أخطاء API خام للمتصفح).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ============ تحديد مسار المجلد الخاص ============
$privateDir = getenv('RADMA_PRIVATE_DIR');
if ($privateDir === false || $privateDir === '' || !is_dir($privateDir)) {
    // البنية الافتراضية: private بجوار public_html
    $privateDir = dirname(__DIR__, 2) . '/private';
}
if (!is_dir($privateDir)) {
    respond(false, null, 500);
}

$config = require $privateDir . '/mazbot-config.php';
require $privateDir . '/mazbot-client.php';
require $privateDir . '/pricing/rolling-shutter.php';
require $privateDir . '/pricing/overhead.php';

// ============ أدوات مساعدة ============

function respond(bool $success, ?string $requestId, int $httpStatus): never
{
    http_response_code($httpStatus);
    echo json_encode(['success' => $success, 'requestId' => $requestId], JSON_UNESCAPED_UNICODE);
    exit;
}

function logLine(string $privateDir, array $fields): void
{
    $line = date('c') . ' ' . json_encode($fields, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    @file_put_contents($privateDir . '/logs/quote-requests.log', $line, FILE_APPEND | LOCK_EX);
}

/** التحقق من رقم الهاتف العماني وتوحيده إلى 968XXXXXXXX — يطابق منطق Frontend */
function normalizeOmaniPhone(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if (str_starts_with($digits, '00968')) {
        $digits = substr($digits, 2);
    }
    if (preg_match('/^968[79]\d{7}$/', $digits)) {
        return $digits;
    }
    if (preg_match('/^[79]\d{7}$/', $digits)) {
        return '968' . $digits;
    }
    return null;
}

/** Rate limit بسيط لكل IP عبر ملفات عدّاد داخل private/rate-limit */
function checkRateLimit(string $privateDir, array $config): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = $privateDir . '/rate-limit/' . sha1($ip) . '.json';
    $now = time();
    $window = (int) $config['rate_limit_window'];
    $max = (int) $config['rate_limit_max'];

    $timestamps = [];
    if (is_readable($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $timestamps = array_filter($decoded, fn ($t) => is_int($t) && $t > $now - $window);
        }
    }

    if (count($timestamps) >= $max) {
        return false;
    }

    $timestamps[] = $now;
    @file_put_contents($file, json_encode(array_values($timestamps)), LOCK_EX);
    return true;
}

function makeRequestId(string $prefix): string
{
    try {
        $suffix = strtoupper(bin2hex(random_bytes(3)));
    } catch (Throwable) {
        $suffix = strtoupper(substr(md5((string) mt_rand()), 0, 6));
    }
    return $prefix . '-' . date('Ymd') . '-' . $suffix;
}

// ============ استقبال الطلب ============

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, null, 405);
}

$rawInput = file_get_contents('php://input');
$input = json_decode((string) $rawInput, true);
if (!is_array($input)) {
    respond(false, null, 400);
}

if (!checkRateLimit($privateDir, $config)) {
    logLine($privateDir, ['event' => 'rate_limited', 'ip_hash' => sha1($_SERVER['REMOTE_ADDR'] ?? '')]);
    respond(false, null, 429);
}

$calculator = (string) ($input['calculator'] ?? '');
$clientName = trim((string) ($input['clientName'] ?? ''));
$clientPhone = normalizeOmaniPhone((string) ($input['clientPhone'] ?? ''));
$governorate = (string) ($input['governorate'] ?? '');
$state = (string) ($input['state'] ?? '');

if ($clientName === '' || mb_strlen($clientName) > 100 || $clientPhone === null) {
    respond(false, null, 422);
}

// ============ التحقق وإعادة الحساب حسب نوع الحاسبة ============

if ($calculator === 'rolling_shutter') {
    $slatType = (string) ($input['slatType'] ?? '');
    $widthCm = (float) ($input['widthCm'] ?? 0);
    $heightCm = (float) ($input['heightCm'] ?? 0);
    $clientPrice = (float) ($input['estimatedPrice'] ?? 0);

    $typeIndex = rollingShutterTypeIndexByLabel($slatType);
    $statesMap = rollingShutterStates();
    $validState = isset($statesMap[$governorate]) && in_array($state, $statesMap[$governorate], true);

    // حدود منطقية للمقاسات بالسنتيمتر
    $validDims = $widthCm >= 50 && $widthCm <= 2000 && $heightCm >= 50 && $heightCm <= 2000;

    if ($typeIndex === null || !$validState || !$validDims) {
        respond(false, null, 422);
    }

    // السعر المعتمد هو المحسوب في الخادم — لا يُوثق بسعر المتصفح
    $serverCalc = calculateRollingShutterPrice($typeIndex, $state, $widthCm, $heightCm);
    if ($serverCalc === null) {
        respond(false, null, 422);
    }

    $requestId = makeRequestId('RS');
    $priceMismatch = abs($serverCalc['price'] - $clientPrice) > 0.01;

    $messageText = "📩 طلب عرض أسعار جديد – رولينج شتر\n\n"
        . "🆔 رقم الطلب:\n{$requestId}\n\n"
        . "👤 العميل: {$clientName}\n"
        . "📞 رقم الجوال: {$clientPhone}\n\n"
        . "📍 المحافظة: {$governorate}\n"
        . "📍 الولاية: {$state}\n\n"
        . "🚪 نوع البوابة:\nRolling Shutter\n\n"
        . "🔧 نوع الشرائح:\n{$slatType}\n\n"
        . "📏 المقاسات:\n"
        . "العرض: {$widthCm} سم\n"
        . "الارتفاع: {$heightCm} سم\n\n"
        . "💰 السعر التقديري:\n" . number_format($serverCalc['price'], 3, '.', '') . " ريال عماني\n\n"
        . "🌐 المصدر:\nحاسبة بوابات الرولينج شتر – ردما\n\n"
        . "ملاحظة:\nالسعر تقديري ويحتاج إلى التأكيد النهائي من فريق المبيعات.";

    $templateValues = [
        1 => $requestId,
        2 => $clientName,
        3 => $clientPhone,
        4 => $governorate . ' - ' . $state,
        5 => $slatType,
        6 => $widthCm . 'x' . $heightCm . ' سم',
        7 => number_format($serverCalc['price'], 3, '.', '') . ' ريال عماني',
    ];
} elseif ($calculator === 'overhead') {
    $gateType = (string) ($input['gateType'] ?? '');
    $widthCm = (int) ($input['widthCm'] ?? 0);
    $heightCm = (int) ($input['heightCm'] ?? 0);
    $motor = (string) ($input['motor'] ?? '');
    $clientMin = (float) ($input['priceMin'] ?? 0);
    $clientMax = (float) ($input['priceMax'] ?? 0);

    // السعر المعتمد هو المحسوب في الخادم — يتحقق ضمنيًا من النوع والمقاس
    // والمحرك والمحافظة والولاية (أي قيمة خارج الجداول ترفض الطلب)
    $serverCalc = calculateOverheadPrice($gateType, $heightCm, $widthCm, $motor, $governorate, $state);
    if ($serverCalc === null) {
        respond(false, null, 422);
    }

    $requestId = makeRequestId('OH');
    $priceMismatch = $serverCalc['min'] !== (int) $clientMin || $serverCalc['max'] !== (int) $clientMax;

    $messageText = "📩 طلب عرض أسعار جديد – بوابة أوفرهيد\n\n"
        . "🆔 رقم الطلب:\n{$requestId}\n\n"
        . "👤 العميل: {$clientName}\n"
        . "📞 رقم الجوال: {$clientPhone}\n\n"
        . "📍 المحافظة: {$governorate}\n"
        . "📍 الولاية: {$state}\n\n"
        . "🚪 نوع البوابة:\n{$gateType}\n\n"
        . "📏 المقاسات:\n"
        . "العرض: {$widthCm} سم\n"
        . "الارتفاع: {$heightCm} سم\n\n"
        . "⚙️ المحرك:\n{$motor}\n\n"
        . "💰 السعر التقديري:\n{$serverCalc['min']} - {$serverCalc['max']} ريال عماني\n\n"
        . "🌐 المصدر:\nحاسبة بوابات الأوفرهيد – ردما\n\n"
        . "ملاحظة:\nالسعر تقديري ويحتاج إلى التأكيد النهائي من فريق المبيعات.";

    $templateValues = [
        1 => $requestId,
        2 => $clientName,
        3 => $clientPhone,
        4 => $governorate . ' - ' . $state,
        5 => $gateType . ' / ' . $motor,
        6 => $widthCm . 'x' . $heightCm . ' سم',
        7 => $serverCalc['min'] . ' - ' . $serverCalc['max'] . ' ريال عماني',
    ];
} else {
    respond(false, null, 422);
}

if ($priceMismatch) {
    logLine($privateDir, [
        'event' => 'price_mismatch',
        'requestId' => $requestId,
        'calculator' => $calculator,
        'clientPrice' => $input['estimatedPrice'] ?? [$input['priceMin'] ?? null, $input['priceMax'] ?? null],
        'serverPrice' => $serverCalc,
    ]);
}

// ============ الإرسال إلى أرقام الاستقبال (كل رقم مستقل) ============

$results = [];
foreach ($config['recipients'] as $recipient) {
    $result = sendQuoteToRecipient($config, $recipient, $messageText, $templateValues);
    $results[$recipient] = $result;

    logLine($privateDir, [
        'event' => 'send_attempt',
        'requestId' => $requestId,
        'calculator' => $calculator,
        'recipient' => $recipient,
        'success' => $result['success'],
        'httpStatus' => $result['status'],
        'error' => $result['error'],
    ]);
}

$successCount = count(array_filter($results, fn ($r) => $r['success']));
$overall = match (true) {
    $successCount === count($results) => 'success',
    $successCount > 0 => 'partial_success',
    default => 'failure',
};

logLine($privateDir, [
    'event' => 'request_result',
    'requestId' => $requestId,
    'calculator' => $calculator,
    'outcome' => $overall,
    'successCount' => $successCount,
    'recipientCount' => count($results),
]);

// نجاح رقم واحد على الأقل = نجاح من منظور العميل
respond($successCount > 0, $requestId, $successCount > 0 ? 200 : 502);
