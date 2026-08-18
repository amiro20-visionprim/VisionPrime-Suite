# سند ۴۱ — استقرار تولید: سرور visionprime (Ubuntu 24.04)

> **وضعیت:** ✅ اجرا و تأیید شد — ۲۰۲۶-۰۸-۱۷
> **مرجع:** سند ۳۷ (معماری اتاق فرماندهی) · سند ۴۰ (فاز G) · تصمیمنامه D-034
> **محورها:** مشخصات سرور · نصب استک · استقرار کد + داده · nginx/php-fpm · صف + زمانبند · بهروزرسانی بعدی

---

## مشخصات سرور (تأمینشده توسط مالک)

| مورد | مقدار |
|---|---|
| سیستم عامل | Ubuntu 24.04 LTS (هسته 6.8) — VM `vm-255175` |
| IP عمومی | `45.156.186.6` |
| هاست‌نیم | `visionprime` |
| SSH | پورت `9011`، کاربر `root` |
| RDP ویندوز | پورت `15226` (VM ویندوز جداگانه — استفاده نشده) |
| سخت‌افزار | 3.8GB RAM · 30GB دیسک (26GB آزاد) |
| محل کد | `/var/www/workspace-arena-suite` |

## استک نصب‌شده

- **PHP 8.3.6** (cli + fpm + mbstring/xml/curl/sqlite3/zip/intl/bcmath/gd) — از apt اوبونتو
- **Composer 2.7.1** — از apt (getcomposer.org از سرور مسدود است)
- **Nginx 1.24** + php-fpm (سوکت `/run/php/php8.3-fpm.sock`)
- **Node.js: نصب نشد** — فرانت‌اند محلی build و فقط `public/build` آپلود شد (nodesource از سرور مسدود است)
- **دیتابیس:** SQLite (`database/database.sqlite`) + صف/کش/سشن روی database — بدون نیاز به PostgreSQL/Redis

## نکتهٔ مهم شبکه

از این سرور **getcomposer.org، packagist.org و deb.nodesource.com مسدود/نیمه‌مسدود** هستند (گیت‌هاب در دسترس است). بنابراین:
- ریپو از گیت‌هاب clone شد ✓
- `vendor/` به‌صورت آرشیو از سیستم توسعه منتقل شد + `composer dump-autoload -o`
- فرانت‌اند با node محلی build و `public/build` آپلود شد

## استقرار (یک‌بار اجرا شد)

```bash
# ۱) کد
git clone -b new-branch https://github.com/amiro20-visionprim/workspace-arena-suite.git /var/www/

# ۲) وابستگی‌ها
tar xzf vendor.tgz -C /var/www/workspace-arena-suite/vision-prime/
composer dump-autoload -o

# ۳) env (از .env.example با sed)
APP_ENV=production · APP_DEBUG=false · APP_URL=http://45.156.186.6
DB_CONNECTION=sqlite · DB_DATABASE=database/database.sqlite
QUEUE_CONNECTION=database · CACHE_STORE=database · SESSION_DRIVER=database

# ۴) کلید + دیتابیس + لینک‌ها
php artisan key:generate
# database.sqlite از سیستم توسعه منتقل شد (داده‌های واقعی) — migrate چیزی برای اجرا نداشت
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache public/build database

# ۵) nginx: /etc/nginx/sites-available/visionprime → root …/vision-prime/public
# ۶) صف: systemd service visionprime-queue (php artisan queue:work database)
# ۷) زمانبند: cron → php artisan schedule:run هر دقیقه
```

## سرویس‌های فعال

| سرویس | وضعیت |
|---|---|
| `nginx` | active |
| `php8.3-fpm` | active |
| `visionprime-queue` (systemd) | active — `queue:work database --sleep=3 --tries=3` |
| cron (root) | `* * * * * php artisan schedule:run` |

## تأیید (همگی سبز)

- `http://45.156.186.6/` → 200 · `/login` → 200 · `/build/*` → 200 (از بیرون تست شد)
- ورود `superadmin@visionprime.test` → 302 به `/app/dashboard` · `/platform/dashboard` → 200
- داده‌ها منتقل شد: ۹ کاربر، ۱ سازمان، ۱ سایت، ۲۷ دستور تقویم، پرداخت‌ها، sms_logs
- صف: ارسال نوتیفیکیشن تست → jobs remaining: 0 (worker پردازش کرد)
- `schedule:list`: همهٔ ۱۱ task (از جمله `SendWeeklyReport` جمعه) ثبت‌اند

## به‌روزرسانی بعدی (دیپلوی جدید)

```bash
ssh -p 9011 root@45.156.186.6
cd /var/www/workspace-arena-suite && git pull origin new-branch
cd vision-prime
composer dump-autoload -o          # اگر وابستگی جدید هست، vendor را از سیستم توسعه آپلود کن
php artisan migrate --force
# اگر فرانت‌اند تغییر کرده: محلی npm run build → آپلود public/build
chown -R www-data:www-data storage bootstrap/cache public/build
systemctl restart visionprime-queue && systemctl reload nginx
```

## گام‌های آینده (پیشنهادی)

- **دامنه + HTTPS:** `visionprime-suite.ir` را به این IP وصل کن + certbot (Let's Encrypt) — حالا HTTP خالص است
- کلیدهای واقعی در env: `OWNER_PHONE` / `TELEGRAM_BOT_TOKEN` / `KAVENEGAR_API_KEY` / `ZARINPAL_MERCHANT_ID` / `PLATFORM_AI_API_KEY`
- بکاپ DB را به جای دیگری (نه فقط storage/backups سرور) منتقل کن
- Dockerize برای تکرارپذیری (اختیاری)
