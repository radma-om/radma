# موقع ردما للبوابات الإلكترونية — radma.co

موقع الشركة (واجهة React مبنية مسبقًا) + نموذج "تواصل معنا"، مع خلفية PHP صغيرة ترسل الطلبات إلى واتساب عبر MazBot. حاسبتا الأسعار (رولينج شتر وأوفرهيد) أصبحتا نظامًا مستقلًا منفصلًا تمامًا — انظر "الحاسبات" أدناه.

## الحاسبات

الحاسبتان لم تعودا جزءًا من هذا المستودع، بل نظام Node.js مستقل بمستودعه الخاص (`mohdhadhrami/Aluminum`) وله لوحة إدارة أسعار خاصة به:
- رولينج شتر: **https://calcshutter.radma.co/** (رابط للعميل، وأيضًا iframe في صفحة `/calculators`)
- أوفرهيد: **https://calcoverhead.radma.co/** (رابط للعميل) — داخل iframe في `/calculators` تُستخدم `https://calcshutter.radma.co/overhead` بدلاً منه (تفاديًا لتضمين iframe داخل iframe)
- لوحة إدارة الأسعار: **https://calcshutter.radma.co/admin**

`.htaccess` يحوّل (301) الروابط القديمة `radma.co/price-calc/rolling-shutter/` و`radma.co/price-calc/overhead/` للنظام الجديد — هذه صفحات ورموز خادم قديمة حُذفت من هذا المستودع بتاريخ 2026-09-30 (لم يعد أي شيء في الموقع يستدعيها).

## البنية

| المسار | الوصف |
|---|---|
| `index.html` + `assets/` | واجهة الموقع (ناتج بناء React — انظر الملاحظة أدناه) |
| `price-calc/api/submit-contact.php` | نقطة الاستقبال الوحيدة المتبقية هنا — لنموذج "تواصل معنا" فقط |
| `private/` | منطق الخادم (عميل MazBot) — **محجوب تمامًا عن الويب** |
| `.htaccess` | توجيه صفحات الموقع (SPA) + الحماية + الضغط + التخزين المؤقت |
| `router.php`, `start-server.bat`, `stop-server.bat` | تشغيل الموقع محليًا للتجربة (لا تُخدَّم على الويب) |
| `update-site.bat` | نشر التعديلات بنقرة واحدة (commit + push) |
| `radma-logo.png` | شعار الموقع (أيقونة المتصفح + صور المشاركة في `index.html`) |

## ما لا يوجد في Git عمدًا (المستودع عام)

- `private/mazbot-config.php` — مفاتيح MazBot وكلمة المرور وأرقام الاستقبال. موجود على السيرفر فقط.
  للإنشاء من الصفر: انسخ `private/mazbot-config.example.php` إلى `private/mazbot-config.php` واملأ القيم.
- `private/logs/`, `private/cache/`, `private/rate-limit/` — بيانات تشغيلية (المجلدات فقط محفوظة عبر `.gitkeep`).

> ⚠️ لا تضع أي مفتاح أو كلمة مرور في أي ملف داخل Git. تحقّق قبل كل `commit` بـ `git status`.

## تحديث الموقع

الطريقة الأسهل: بعد أي تعديل على ملفات المجلد، شغّل **`update-site.bat`** (نقرتان)، اكتب وصفًا قصيرًا للتعديل ثم Enter — يرفع التعديل إلى GitHub وينشره على radma.co تلقائيًا. الملف يرفض العمل إن اكتشف أن ملف الأسرار أصبح ضمن Git.

أو يدويًا من الطرفية:

```bash
git add -A
git commit -m "وصف التعديل"
git push
```

الاستضافة (Hostinger → radma.co → Advanced → Git) مربوطة بهذا المستودع، فيُنشر كل `push` على فرع `main` تلقائيًا إلى `public_html` خلال ثوانٍ.

## التجربة محليًا

شغّل `start-server.bat` (يتطلب PHP في PATH) ثم افتح `http://localhost:8090`.
تنبيه: إن كان `private/mazbot-config.php` موجودًا محليًا فنموذج التواصل سيرسل رسائل واتساب **حقيقية**؛ اضبط `MAZBOT_DRY_RUN=1` للتجربة الآمنة.

## ملاحظات مهمة

- **كود React غير موجود هنا**: `assets/index-*.js` و`assets/index-*.css` ناتج بناء جاهز (Minified). التعديلات تمت عليه مباشرة، ولا يوجد مشروع مصدري (`src/`) في هذا المستودع. أي تعديل مستقبلي في الواجهة يتم بتحرير هذه الملفات مباشرة.
- **GitHub Pages**: هذا الموقع يعتمد على مسارات مطلقة (`/assets/...`) وعلى PHP، لذا لن يعمل بشكل صحيح على `radma-om.github.io/radma/`. الموقع الحقيقي هو **https://radma.co**.
