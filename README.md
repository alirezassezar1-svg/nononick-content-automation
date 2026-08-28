# nononick-content-automation

سیستم نیمه‌خودکار تولید مقاله برای nononick.ir

## جریان کار

1. هر روز کلود (تو همین چت) یه مقاله می‌نویسه و به‌صورت فایل JSON تو پوشه `drafts/` این ریپو push می‌کنه.
2. یه Cron Job رو سرور شتاب، هر ساعت `pull-drafts.php` رو اجرا می‌کنه.
3. اسکریپت فایل‌های جدید تو `drafts/` رو می‌خونه، به‌عنوان **پیش‌نویس** (status = draft) تو جدول `articles` درج می‌کنه، و فایل رو از گیت‌هاب پاک می‌کنه.
4. تو تو ادمین پنل سایت، پیش‌نویس‌ها رو می‌بینی و تأیید/منتشر می‌کنی.

## راه‌اندازی اولیه (یک‌بار)

### ۱. دیتابیس
اگه جدول `articles` نداری، `server-files/schema.sql` رو تو phpMyAdmin اجرا کن.
اگه جدول از قبل داری، مطمئن شو حداقل این ستون‌ها رو داره:
`title, slug, content, status, source, github_source_file`

### ۲. آپلود فایل‌ها رو سرور
از `server-files/` این دو فایل رو آپلود کن (مثلاً تو یه پوشه‌ی خصوصی مثل `admin/automation/`):
- `pull-drafts.php`
- `config.example.php` → کپی کن به `config.php` و مقادیر واقعی (دیتابیس + توکن گیت‌هاب) رو بذار توش.

⚠️ **مهم:** `config.php` هرگز نباید public باشه یا به گیت‌هاب push بشه. تو دیتابیس واقعی و توکن رو داره.

### ۳. ساخت توکن گیت‌هاب برای سرور (Read-only)
یه توکن **fine-grained** جدا و محدود بساز، فقط برای این کار:
- Repository access: Only select repositories → `nononick-content-automation`
- Permissions → Contents: **Read and write** (چون سرور هم می‌خونه هم فایل importشده رو پاک می‌کنه)

این توکن با توکنی که من (کلود) برای push کردن مقاله‌ها استفاده می‌کنم فرق داره — بهتره جدا باشن.

### ۴. تنظیم Cron Job
تو cPanel → Cron Jobs، یه خط جدید اضافه کن:

```
0 * * * * php /home/USERNAME/admin/automation/pull-drafts.php
```

(هر ساعت اجرا می‌شه. مسیر رو با مسیر واقعی هاستت جایگزین کن.)

### ۵. تست دستی (اختیاری)
می‌تونی از مرورگر هم صداش بزنی:
```
https://nononick.ir/admin/automation/pull-drafts.php?token=PULL_SECRET_TOKEN_خودت
```

## فرمت فایل مقاله (drafts/*.json)

```json
{
  "title": "عنوان مقاله",
  "slug": "optional-custom-slug",
  "content": "<p>محتوای HTML کامل مقاله...</p>",
  "excerpt": "خلاصه کوتاه یک یا دو خطی",
  "meta_description": "توضیحات متا برای سئو"
}
```

## بکاپ خودکار روی گوگل درایو

هر بار که فایل جدیدی تو `drafts/` push بشه، ورک‌فلوی
`.github/workflows/drive-backup.yml` خودکار اجرا میشه و یه نسخه از اون
فایل(ها) رو با یه سرویس‌اکانت گوگل، تو یه فولدر مشخص از گوگل درایو
آپلود می‌کنه (به‌عنوان آرشیو/بکاپ، مستقل از دیتابیس سایت).

راه‌اندازی یک‌بار:
1. یه فولدر تو گوگل درایو بساز و با ایمیل سرویس‌اکانت
   (`nononick-drive-bot@creat-506915.iam.gserviceaccount.com`) به نقش
   Editor به اشتراک بذار.
2. شناسه اون فولدر (بخشی از URL بعد از `/folders/`) رو به‌عنوان سکرت
   `GDRIVE_FOLDER_ID` تو Settings → Secrets and variables → Actions اضافه کن.
3. سکرت `GOOGLE_SERVICE_ACCOUNT_JSON` از قبل ست شده.

جزئیات اسکریپت تو `scripts/backup_to_drive.py`.

## امنیت
- `config.php` هیچ‌وقت commit نمی‌شه (تو `.gitignore` هست)
- توکن‌های گیت‌هاب رو هر چند وقت یک‌بار عوض کن (Regenerate)
- این ریپو Private نگه داشته بشه
