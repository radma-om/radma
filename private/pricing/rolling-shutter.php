<?php
/**
 * تسعير الرولينج شتر — نسخة Server-side مطابقة حرفيًا لحاسبة Frontend
 * (public_html/rolling-shutter/index.html)
 *
 * ⚠️ أي تعديل سعري يجب أن يتم في الملفين معًا (Frontend + هذا الملف).
 */

declare(strict_types=1);

/** أنواع الشرائح وأسعار المتر المربع — بنفس ترتيب Frontend (index يبدأ من 1) */
function rollingShutterTypes(): array
{
    return [
        1 => ['label' => 'الإيراني الأبيض | بيج | فضي | رمادي | أسود Grade C', 'price' => 14],
        2 => ['label' => 'التركي الأبيض | فضي | رمادي | أسود Grade B', 'price' => 16.5],
        3 => ['label' => 'التركي الخشبي Grade B', 'price' => 26.0],
        4 => ['label' => 'التركي الأبيض | بيج Grade A', 'price' => 20.5],
        5 => ['label' => 'العماني الأبيض سماكة 1.1 ملم', 'price' => 21.5],
        6 => ['label' => 'العماني الملون سماكة 1.1 ملم', 'price' => 19.5],
        7 => ['label' => 'العماني الأبيض سماكة 1.5 ملم', 'price' => 27],
        8 => ['label' => 'العماني الملون سماكة 1.5 ملم', 'price' => 24.5],
    ];
}

/** المحافظات وولاياتها */
function rollingShutterStates(): array
{
    return [
        'الداخلية' => ['نزوى', 'بهلاء', 'الحمراء', 'أدم', 'إزكي', 'منح', 'سمائل', 'بدبد', 'الجبل الأخضر'],
        'مسقط' => ['مسقط', 'السيب', 'بوشر', 'مطرح', 'العامرات'],
        'جنوب الباطنة' => ['المصنعة', 'بركاء'],
        'شمال الشرقية' => ['إبراء', 'سناو', 'المضيبي', 'دماء والطائيين'],
        'الظاهرة' => ['عبري'],
    ];
}

/** أسعار التركيب حسب الولاية */
function rollingShutterInstallationPrices(): array
{
    return [
        'نزوى' => 60, 'بهلاء' => 80, 'الحمراء' => 80, 'أدم' => 90,
        'إزكي' => 80, 'منح' => 70, 'سمائل' => 80, 'بدبد' => 90,
        'الجبل الأخضر' => 110, 'مسقط' => 100, 'السيب' => 90, 'بوشر' => 90,
        'مطرح' => 100, 'العامرات' => 100, 'المصنعة' => 110, 'بركاء' => 90,
        'إبراء' => 100, 'المضيبي' => 100, 'دماء والطائيين' => 110,
        'سناو' => 100, 'عبري' => 110,
    ];
}

/** البحث عن رقم النوع من التسمية */
function rollingShutterTypeIndexByLabel(string $label): ?int
{
    foreach (rollingShutterTypes() as $index => $type) {
        if ($type['label'] === $label) {
            return $index;
        }
    }
    return null;
}

/**
 * إعادة حساب السعر Server-side بنفس معادلة Frontend حرفيًا.
 *
 * @return array{price:float, areaM2:float}|null null إذا كانت المدخلات خارج النطاق
 */
function calculateRollingShutterPrice(int $typeIndex, string $state, float $widthCm, float $heightCm): ?array
{
    $types = rollingShutterTypes();
    $installation = rollingShutterInstallationPrices();

    if (!isset($types[$typeIndex], $installation[$state]) || $widthCm <= 0 || $heightCm <= 0) {
        return null;
    }

    // إضافة الزيادات للأبعاد
    $addedWidth = ($typeIndex >= 4 && $typeIndex <= 8) ? 20 : 15;
    $addedHeight = 60;

    $areaM2 = (($widthCm + $addedWidth) / 100) * (($heightCm + $addedHeight) / 100);

    $sheetPrice = $areaM2 * $types[$typeIndex]['price'];

    // الملحقات: النوع 1 = 40، الأنواع 2-6 = 51، الأنواع 7-8 = 60
    $accessoriesPrice = 40;
    if ($typeIndex >= 7) {
        $accessoriesPrice = 60;
    } elseif ($typeIndex >= 2) {
        $accessoriesPrice = 51;
    }

    $basePrice = 15;
    $motorPrice = 70;

    // الطلاء الإضافي: النوع 6 = 60، النوع 8 = 70
    $paintPrice = 0;
    if ($typeIndex === 6) {
        $paintPrice = 60;
    } elseif ($typeIndex === 8) {
        $paintPrice = 70;
    }

    $total = $sheetPrice + $accessoriesPrice + $basePrice + $motorPrice + $paintPrice + $installation[$state];

    return [
        'price' => round($total, 3),
        'areaM2' => round($areaM2, 3),
    ];
}
