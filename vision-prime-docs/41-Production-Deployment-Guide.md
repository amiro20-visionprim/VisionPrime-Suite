# سند ۴۱ — راهنمای استقرار تولید (Production Deployment)

> **وضعیت:** ✅ استقرار انجام شد — ۲۰۲۶-۰۸-۱۷
> **مرجع:** سند ۴۰ (فاز G) · تصمیمنامه D-034
> **سرور:** Ubuntu 24.04 LTS · ۴۵.۱۵۶.۱۸۶.۶ (SSH پورت ۹۰۱۱) · دامنه: `visionprime-suite.ir`

---

## ۱) مشخصات سرور

| مورد | مقدار |
|---|---|
| سیستم عامل | Ubuntu 24.04 LTS (سرور ابری ویندوزی RDP پورت ۱۵۲۲۶ جداگانه) |
| IP | 45.156.186.6 |
| SSH | پورت 9011 — کاربر `root` |
| رم | 3.8 GB (3.4 آزاد) |
| دیسک | 30 GB (26 آزاد) |
| استک | PHP 8.3.6-fpm · nginx 1.24 · Composer 2.7.1 · Git |
| دیتابیس | SQLite (`database/database.sqlite`) — بدون نیاز به سرور DB جدا |
| صف/کش/سشن | database (همگی روی همان SQLite) |

## ۲) چیدمان فایل‌ها

- **ریپو:** `/var/www/workspace-arena-suite` (clone از GitHub، برنچ `new-branch`)
- **اپ:** `/var/www/workspace-arena-suite/vision-prime`
- **سایت‌های استاتیک:** `/var/www/sites/<domain>/` (هر دامنه یک پوشه)
- **الگوی vhost استاتیک:** `/etc/nginx/sites-available/static-template`
- **vhost اصلی:** `/etc/nginx/sites-available/visionprime` (server_name: `45.156.186.6` + `visionprime-suite.ir` + `www.visionprime-suite.ir`)

## ۳) سرویس‌ها

| سرویس | روش | وضعیت |
|---|---|---|
| nginx | systemd | active |
| php8.3-fpm | systemd (socket `/run/php/php8.3-fpm.sock`) | active |
| صف (`queue:work database`) | systemd `visionprime-queue` | active |
| زمان‌بند (`schedule:run`) | cron ریشه هر دقیقه | فعال |

## ۴) نکات ویژه استقرار (موانع شبکه)

سرور به **getcomposer.org و packagist.org دسترسی ندارد** (تحریم/فیلترینگ شبکه). راهکار:
- Composer از apt نصب شد (2.7.1).
- `vendor/` از ماشین توسعه آپلود و با `composer dump-autoload -o` بازسازی شد (۷۰۲۸ کلاس).
- فرانت‌اند روی ماشین توسعه build شد و فقط خروجی `public/build/` آپلود شد (سرور به Node نیاز ندارد).
- برای به‌روزرسانی بعدی: pull کد → آپلود `public/build` جدید (اگر فرانت تغییر کرده) → `composer dump-autoload -o` (اگر وابستگی جدید آمد، `vendor` کامل را آپلود کن) → `php artisan migrate --force`.

## ۵) دامنه و DNS

- دامنه `visionprime-suite.ir` ثبت شده ولی **DNS هنوز ست نشده** — باید در پنل ایرنیک ثبت شود (بخش ۶).
- APP_URL و GSC_REDIRECT_URI روی `http://visionprime-suite.ir` تنظیم شده.
- بعد از انتشار DNS: گواهی TLS با Let's Encrypt (`certbot --nginx -d visionprime-suite.ir -d www.visionprime-suite.ir`).

## ۶) رکوردهای DNS برای ثبت در ایرنیک

| نوع | نام (Host) | مقدار | TTL |
|---|---|---|---|
| A | `visionprime-suite.ir` | `45.156.186.6` | 3600 (پیش‌فرض) |
| A | `www` | `45.156.186.6` | 3600 (پیش‌فرض) |

نام‌سرورها (اگر در پنل ایرنیک نمایش داده شود): `ns1.irnic.ir` و `ns2.irnic.ir` (همان پیش‌فرض ثبت). انتشار DNS معمولاً ۱ تا ۲۴ ساعت طول می‌کشد.

## ۷) داده‌ها

- دیتابیس واقعی توسعه (کاربران، سازمان، سایت، ۲۷ کامند تقویم محتوا، پرداخت‌ها، sms_logs) **همراه منتقل شد** — اپ با همان داده‌ها بالا آمد.
- بکاپ خودکار: `platform:backup-db` هر شب ۲۲:۳۰ → `storage/backups/` (۷ نسخه).
- ورود: `superadmin@visionprime.test` (پسورد پیش‌فرض seeder — پس از اولین ورود حتماً عوض شود).

## ۸) تأییدهای انجام‌شده

- ✅ `http://45.156.186.6/` و `/login` → 200 (عمومی)
- ✅ ورود سوپرادمین → 302 به داشبورد · `/platform/dashboard` → 200
- ✅ صف: نوتیفیکیشن تستی dispatch شد → jobs remaining: 0 (کارگر صف پردازش کرد)
- ✅ زمان‌بند: ۱۱ تسک در `schedule:list` (گزارش هفتگی جمعه، بکاپ، یادآوری‌ها، حلقهٔ یادگیری…)
- ✅ `Host: visionprime-suite.ir` و `www` → 200 (دامنه از سمت سرور آماده است)

## ۹) امنیت

- APP_DEBUG=false · APP_ENV=production · APP_KEY تولید شد.
- بدون فایروال فعال (ufw نصب نیست) — پورت‌های باز: 80 و 22(9011). پیشنهاد: فایروال + SSH-key-only.
- `.env` خارج از `public/` — از بیرون قابل دسترسی نیست.

## ۱۰) DNS خودمیزبان (bind9) — 2026-08-18

- ایرنیک فقط ثبتکننده است؛ رکورد A را نگه نمیدارد. برای دامنهٔ `.ir` باید نامسرور (host object) تعریف شود.
- روی سرور **BIND 9.18** نصب شد (`apt install bind9`).
- زون `visionprime-suite.ir` در `/etc/bind/zones/db.visionprime-suite.ir`:
  - `@` و `www` → `45.156.186.6`
  - NS: `ns1.visionprime-suite.ir` + `ns2.visionprime-suite.ir` (هر دو → `45.156.186.6`)
- تأیید از بیرون: `nslookup visionprime-suite.ir 45.156.186.6` → `45.156.186.6` ✓ · پورت 53 TCP باز ✓
- **اقدام کاربر در ایرنیک** (بخش «ویرایش ردیفهای کارگزاری نام و میزبانی دامنه»):
  - ردیف ۱: نام کارگزار `ns1.visionprime-suite.ir` — آیپی `45.156.186.6`
  - ردیف ۲: نام کارگزار `ns2.visionprime-suite.ir` — آیپی `45.156.186.6`
- پس از انتشار (تا ۲۴ ساعت): نصب SSL و ریدایرکت HTTPS.
- الگوی سایتهای استاتیک بعدی: زون جدید در bind9 + vhost nginx + همان دو ردیف NS با آیپی سرور (یا subdomain A).
