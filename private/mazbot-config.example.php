<?php
/**
 * نموذج إعدادات MazBot — ردما للبوابات الإلكترونية
 *
 * ⚠️ هذا ملف نموذجي بقيم وهمية وهو الوحيد المسموح بوجوده في Git.
 *    الملف الفعلي private/mazbot-config.php يحتوي أسرارًا حقيقية ولا يُرفع إلى GitHub أبدًا
 *    (مُستثنى في .gitignore) — يُنشأ يدويًا على السيرفر بنسخ هذا النموذج وتعبئة القيم.
 *
 * الأولوية للقيم من Environment Variables إن وُجدت، وإلا تُستخدم القيم المكتوبة هنا.
 */

if (!function_exists('radma_env')) {
    function radma_env(string $key, string $default): string
    {
        $value = getenv($key);
        return ($value !== false && $value !== '') ? $value : $default;
    }
}

return [
    // ================= بيانات الاعتماد =================
    'api_key' => radma_env('MAZBOT_API_KEY', 'CHANGE_ME'),

    // حساب موظف (client-staff) — حسابات المالك (client-admin) لا تعمل مع API
    'staff_email' => radma_env('MAZBOT_STAFF_EMAIL', 'CHANGE_ME@example.com'),
    'staff_password' => radma_env('MAZBOT_STAFF_PASSWORD', 'CHANGE_ME'),

    // ================= إعدادات الإرسال =================
    // 'message'  → رسالة نصية عادية (داخل نافذة 24 ساعة)
    // 'template' → رسالة قالب معتمد (خارج النافذة)
    'send_mode' => radma_env('MAZBOT_SEND_MODE', 'template'),

    // قالب حاسبتي الأسعار (7 متغيرات)
    'template_id' => radma_env('MAZBOT_TEMPLATE_ID', ''),

    // قالب نموذج "تواصل معنا" (5 متغيرات)
    'contact_template_id' => radma_env('MAZBOT_CONTACT_TEMPLATE_ID', ''),

    // أرقام استقبال الطلبات بصيغة دولية بدون + (تُرسل لكل رقم بشكل مستقل)
    // ملاحظة: كل رقم جديد يجب أن يراسل رقم واتساب العمل مرة واحدة أولًا ليستقبل القوالب.
    'recipients' => ['968XXXXXXXX', '968XXXXXXXX'],

    // ================= إعدادات تقنية =================
    'base_url' => radma_env('MAZBOT_BASE_URL', 'https://mazbot.net/api'),
    'endpoint_root' => radma_env('MAZBOT_ENDPOINT_ROOT', 'https://mazbot.net'),
    'connect_timeout' => 10,
    'request_timeout' => 20,
    'token_cache_ttl' => 45 * 60,

    // وضع تجريبي: true = لا يتصل بـ MazBot فعليًا ويسجّل في اللوق فقط
    'dry_run' => radma_env('MAZBOT_DRY_RUN', '0') === '1',

    // ================= Rate Limit =================
    'rate_limit_max' => 5,
    'rate_limit_window' => 600,
];
