<?php
/**
 * تسعير الأوفرهيد — نسخة Server-side مطابقة حرفيًا لحاسبة Frontend
 * (public_html/overhead/index.html)
 *
 * ⚠️ أي تعديل سعري يجب أن يتم في الملفين معًا (Frontend + هذا الملف).
 */

declare(strict_types=1);

/** جداول أسعار البوابات: [النوع][الارتفاع][العرض] => [min, max] */
function overheadGatePrices(): array
{
    return [
        'Type A' => [
            250 => [415 => [355, 375], 455 => [370, 385], 615 => [470, 485]],
            300 => [415 => [465, 495], 455 => [485, 505], 615 => [625, 635]],
        ],
        'Type B' => [
            250 => [370 => [270, 290], 440 => [295, 315], 550 => [320, 345], 600 => [350, 380]],
        ],
    ];
}

/** أسعار المحركات */
function overheadMotorPrices(): array
{
    return [
        'المكينة الإيطالية 1200N' => 145,
        'المكينة الإيطالية 1000N' => 135,
        'المكينة الصينية 1500N' => 110,
    ];
}

/** أسعار التركيب: [المحافظة][الولاية] => السعر */
function overheadInstallationPrices(): array
{
    return [
        'الداخلية' => [
            'نزوى' => 80, 'بهلاء' => 90, 'الحمراء' => 90, 'أدم' => 100,
            'إزكي' => 90, 'منح' => 90, 'سمائل' => 100, 'بدبد' => 100, 'الجبل الأخضر' => 140,
        ],
        'مسقط' => [
            'مسقط' => 110, 'السيب' => 100, 'بوشر' => 100, 'مطرح' => 100, 'العامرات' => 110,
        ],
        'جنوب الباطنة' => [
            'المصنعة' => 120, 'بركاء' => 110,
        ],
        'شمال الشرقية' => [
            'إبراء' => 110, 'المضيبي' => 110, 'دماء والطائيين' => 110, 'سناو' => 110,
        ],
        'الظاهرة' => [
            'عبري' => 115,
        ],
    ];
}

/**
 * إعادة حساب نطاق السعر Server-side بنفس معادلة Frontend حرفيًا.
 *
 * @return array{min:int, max:int}|null null إذا كانت المدخلات خارج النطاق
 */
function calculateOverheadPrice(string $gateType, int $heightCm, int $widthCm, string $motor, string $governorate, string $state): ?array
{
    $gates = overheadGatePrices();
    $motors = overheadMotorPrices();
    $installation = overheadInstallationPrices();

    $gateRange = $gates[$gateType][$heightCm][$widthCm] ?? null;
    $motorPrice = $motors[$motor] ?? null;
    $installPrice = $installation[$governorate][$state] ?? null;

    if ($gateRange === null || $motorPrice === null || $installPrice === null) {
        return null;
    }

    return [
        'min' => $gateRange[0] + $motorPrice + $installPrice,
        'max' => $gateRange[1] + $motorPrice + $installPrice,
    ];
}
