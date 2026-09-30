# موقع ردما للبوابات الإلكترونية — radma.co

موقع الشركة (واجهة React مبنية مسبقًا) + حاسبتا الأسعار + نموذج "تواصل معنا"، مع خلفية PHP صغيرة ترسل الطلبات إلى واتساب عبر MazBot.

## البنية

| المسار | الوصف |
|---|---|
| `index.html` + `assets/` | واجهة الموقع (ناتج بناء React — انظر الملاحظة أدناه) |
| `price-calc/` | `api/submit-contact.php` لا يزال يُستخدم لنموذج "تواصل معنا". الحاسبتان المعتمدتان الآن على النظام المستقل: الرولينج شتر **https://calcshutter.radma.co/** والأوفرهيد **https://calcoverhead.radma.co/** — صفحات `price-calc/overhead/` و`price-calc/rolling-shutter/` قديمة، و`.htaccess` يحوّلها (301) للنظام الجديد |
| `private/` | منطق الخادم (تسعير + عميل MazBot) — **محجوب تمامًا عن الويب** |
| `.htaccess` | توجيه صفحات الموقع (SPA) + الحماية + الضغط + التخزين المؤقت |
| `router.php`, `start-server.bat`, `stop-server.bat` | تشغيل الموقع محليًا للتجربة (لا تُخدَّم على الويب) |
| `update-site.bat` | نشر التعديلات بنقرة واحدة (commit + push) |
| `offer.jpg`, `radma-logo.png` | صور قديمة في المستودع — روابطها قد تكون مستخدمة خارجيًا، فلا تُحذف |

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
تنبيه: إن كان `private/mazbot-config.php` موجودًا محليًا فالحاسبات ونموذج التواصل سترسل رسائل واتساب **حقيقية**؛ اضبط `MAZBOT_DRY_RUN=1` للتجربة الآمنة.

## ملاحظات مهمة

- **كود React غير موجود هنا**: `assets/index-*.js` و`assets/index-*.css` ناتج بناء جاهز (Minified). التعديلات تمت عليه مباشرة، ولا يوجد مشروع مصدري (`src/`) في هذا المستودع. أي تعديل مستقبلي في الواجهة يتم بتحرير هذه الملفات مباشرة.
- **أسعار الحاسبتين** (رولينج شتر وأوفرهيد) تُدار الآن من لوحة الإدارة في **https://calcshutter.radma.co/admin** ولم تعد في هذا المستودع.
- **GitHub Pages**: هذا الموقع يعتمد على مسارات مطلقة (`/assets/...`) وعلى PHP، لذا لن يعمل بشكل صحيح على `radma-om.github.io/radma/`. الموقع الحقيقي هو **https://radma.co**.
auto-deploy verification test 2026-09-30T03:29:17Z
