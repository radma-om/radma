<?php
/**
 * Endpoint استقبال نموذج "تواصل معنا" — ردما للبوابات الإلكترونية
 *
 * يستقبل JSON من نموذج التواصل في الموقع الرئيسي، يتحقق من البيانات،
 * ثم يرسل رسالة قالب واتساب عبر MazBot API إلى أرقام الاستقبال —
 * كل رقم بشكل مستقل، بنفس منطق حاسبتي الأسعار.
 *
 * الاستجابة للعميل عامة دائمًا: {"success": bool, "requestId": string|null}
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ============ تحديد مسار المجلد الخاص ============
$privateDir = getenv('RADMA_PRIVATE_DIR');
if ($privateDir === false || $privateDir === '' || !is_dir($privateDir)) {
    $privateDir = dirname(__DIR__, 2) . '/private';
}
if (!is_dir($privateDir)) {
    respond(false, null, 500);
}

$config = require $privateDir . '/mazbot-config.php';
require $privateDir . '/mazbot-client.php';

// ============ إعدادات نموذج "تواصل معنا" (ليست أسرارًا) ============
// قالب واتساب المعتمد في MazBot: radma_website (5 متغيرات)
//   {{1}} رقم الطلب، {{2}} الاسم، {{3}} الجوال، {{4}} البريد، {{5}} الرسالة
// يُرسل من رقم واتساب العمل 96890660005 إلى الأرقام التالية (كل رقم مستقل).
const CONTACT_TEMPLATE_ID = 3368;
const CONTACT_RECIPIENTS = ['96890660001', '96890660002', '96899887897'];

// قالب احتياطي: إن رفض MazBot القالب 3368 لرقمٍ ما، يُعاد الإرسال لذلك الرقم بالقالب
// المضبوط في private/mazbot-config.php ('contact_template_id') إن وُجد ومختلفًا.
$fallbackTemplateId = (int) ($config['contact_template_id'] ?? 0);

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

/** Rate limit بسيط لكل IP عبر ملفات عدّاد داخل private/rate-limit (مشترك مع الحاسبات) */
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

function makeRequestId(): string
{
    try {
        $suffix = strtoupper(bin2hex(random_bytes(3)));
    } catch (Throwable) {
        $suffix = strtoupper(substr(md5((string) mt_rand()), 0, 6));
    }
    return 'CT-' . date('Ymd') . '-' . $suffix;
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

$name = trim((string) ($input['name'] ?? ''));
$phone = normalizeOmaniPhone((string) ($input['phone'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$message = trim((string) ($input['message'] ?? ''));

if ($name === '' || mb_strlen($name) > 100
    || $phone === null
    || $message === '' || mb_strlen($message) > 1000
    || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
) {
    respond(false, null, 422);
}

$requestId = makeRequestId();

$messageText = "📩 رسالة جديدة من نموذج التواصل – ردما\n\n"
    . "🆔 رقم الطلب:\n{$requestId}\n\n"
    . "👤 الاسم: {$name}\n"
    . "📞 رقم الجوال: {$phone}\n"
    . ($email !== '' ? "📧 البريد الإلكتروني: {$email}\n" : '')
    . "\n💬 الرسالة:\n{$message}\n\n"
    . "🌐 المصدر:\nنموذج التواصل – موقع ردما";

$templateValues = [
    1 => $requestId,
    2 => $name,
    3 => $phone,
    4 => $email !== '' ? $email : '-',
    5 => $message,
];

// ============ الإرسال إلى أرقام الاستقبال (كل رقم مستقل) ============

$results = [];
foreach (CONTACT_RECIPIENTS as $recipient) {
    $templateId = CONTACT_TEMPLATE_ID;
    $result = sendQuoteToRecipient($config, $recipient, $messageText, $templateValues, $templateId);

    logLine($privateDir, [
        'event' => 'send_attempt',
        'requestId' => $requestId,
        'calculator' => 'contact_form',
        'recipient' => $recipient,
        'templateId' => $templateId,
        'success' => $result['success'],
        'httpStatus' => $result['status'],
        'error' => $result['error'],
    ]);

    if (!$result['success'] && $fallbackTemplateId > 0 && $fallbackTemplateId !== CONTACT_TEMPLATE_ID) {
        $templateId = $fallbackTemplateId;
        $result = sendQuoteToRecipient($config, $recipient, $messageText, $templateValues, $templateId);

        logLine($privateDir, [
            'event' => 'send_attempt_fallback',
            'requestId' => $requestId,
            'calculator' => 'contact_form',
            'recipient' => $recipient,
            'templateId' => $templateId,
            'success' => $result['success'],
            'httpStatus' => $result['status'],
            'error' => $result['error'],
        ]);
    }

    $results[$recipient] = $result;
}

$successCount = count(array_filter($results, fn ($r) => $r['success']));

logLine($privateDir, [
    'event' => 'request_result',
    'requestId' => $requestId,
    'calculator' => 'contact_form',
    'outcome' => $successCount === count($results) ? 'success' : ($successCount > 0 ? 'partial_success' : 'failure'),
    'successCount' => $successCount,
    'recipientCount' => count($results),
]);

respond($successCount > 0, $requestId, $successCount > 0 ? 200 : 502);
