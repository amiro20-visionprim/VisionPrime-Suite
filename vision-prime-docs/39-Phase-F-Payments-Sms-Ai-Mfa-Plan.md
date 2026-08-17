# سند ۳۹ — فاز F: درگاه‌های پرداخت، پنل پیامکی، AI Triage و امنیت (MFA)

> **وضعیت:** ✅ اجرا و تأیید شد — ۲۰۲۶-۰۸-۱۷
> **مرجع:** سند ۳۷ (معماری اتاق فرماندهی) · سند ۳۸ (فاز E) · تصمیمنامه D-032
> **محورها:** درگاه‌های پرداخت چندگانه (زرین‌پال + آقای پرداخت + دستی) · پنل پیامکی (کاوه‌نگار) · خلاصهٔ هوشمند Triage · MFA سوپرادمین

---

## چرا این فاز؟

فاز E اتاق فرماندهی را «دیدنی» کرد (KPI، صف تصمیم، رصد فنی) اما سه قابلیت عملیاتی باقی مانده بود:

1. **درگاه پرداخت واقعی** — سند ۳۸ (E3-03) ادعای abstraction درگاه را داشت اما در کد فقط پرداخت دستی وجود داشت. مالک باید بتواند بین درگاه‌های ایرانی جابه‌جا شود.
2. **پنل پیامکی** — هیچ کانال SMS وجود نداشت؛ اعلان‌های مهم (معوق، یادآوری، اضطراری) فقط از طریق نوتیفیکیشن داخلی و تلگرام می‌رسیدند.
3. **امنیت** — اتاق فرماندهی بالاترین دسترسی سیستم بود و هیچ لایهٔ دوم احراز هویت نداشت.

## تصمیمات معماری (ثبت‌شده در D-032)

### F-01 — درگاه‌های پرداخت چندگانه
- **قرارداد `PaymentGateway`** (Contracts): چهار متد `key() / label() / initiate() / verify()` — افزودن درگاه جدید = یک کلاس درایور + یک خط رجیستر در `PaymentGatewayManager`.
- **درایورها:** `ZarinpalGateway` (API v4 واقعی)، `AqayepardakhtGateway` (آقای پرداخت — API واقعی)، `ManualGateway` (دستی/واریز بانکی).
- **حالت sandbox:** بدون `merchant_id`/`pin` در env، درایورها به‌صورت شبیه‌سازی‌شده کار می‌کنند (تراکنش ساختگی + بازگشت موفق) تا توسعه و تست بدون کلید واقعی ممکن باشد. با تنظیم کلید، همان کد به درگاه واقعی وصل می‌شود — **بدون هیچ تغییری در لایهٔ بالاتر.**
- **تبدیل واحد:** سیستم بر حسب ریال (IRT) است؛ در مرز درگاه (زرین‌پال و آقای پرداخت تومان می‌گیرند) بر ۱۰ تقسیم می‌شود.
- **جریان:** `POST /platform/payments/{payment}/pay/{gateway}` → درایور `initiate` (payment → pending + transaction_id) → ریدایرکت به درگاه → بازگشت به `GET /platform/payments/callback/{gateway}/{transaction}` (روت عمومی — بدون لاگین، چون درگاه به آن ریدایرکت می‌کند) → `verify` → markPaid/markFailed + audit.
- **Idempotency:** پیدا کردن payment با `gateway_transaction_id` **یا** `reference`؛ پرداختِ از قبل `paid` دوباره پردازش نمی‌شود.
- **Result page:** یک view سبک blade (`platform/payment-result`) برای نمایش نتیجه به کاربر بازگشتی.

### F-02 — پنل پیامکی
- **قرارداد `SmsSender`** (Contracts): متد `send(to, message, template)` — افزودن پنل جدید = یک کلاس درایور + یک خط رجیستر.
- **درایور اول:** `KavenegarSms` (کاوه‌نگار — API واقعی؛ بدون api_key → sandbox موفق).
- **`SmsManager`:** هر ارسال را در جدول `sms_logs` ثبت می‌کند (درایور، گیرنده، متن، وضعیت، external_id، خطا).
- **UI:** `Platform/Sms.vue` — ارسال پیامک جدید (شماره ۰۹، متن، انتخاب پنل) + تاریخچهٔ ۱۰۰ پیامک آخر با وضعیت.
- **محدودیت‌ها:** اعتبارسنجی شماره با regex `09xxxxxxxxx`؛ throttle ۱۰/دقیقه؛ audit برای هر ارسال.
- **اتصال به اتوماسیون (انجام شد):** `DunningJob` — فاکتور معوق → پیامک هشدار به شمارهٔ org (در `settings.phone`) + ستون `sms_reminder_sent_at` برای جلوگیری از ارسال تکراری؛ `RemindScheduledPublishes` — یادآوری موعد انتشار (یک روز قبل) از طریق پیامک. شمارهٔ سازمان در `organizations.settings.phone` تنظیم می‌شود.

### F-03 — خلاصهٔ هوشمند Triage (AI)
- **`AiTriageSummary`**: تصمیم‌های در انتظار را خلاصه می‌کند.
  - اگر `PLATFORM_AI_API_KEY` تنظیم باشد → فراخوانی chat/completions با پرامپت فارسی «اولویت‌بندی تصمیم‌ها» (سرویس OpenAI-compatible).
  - بدون کلید → **fallback قطعی و آفلاین** (`rule`): شمارش بحرانی/هشدار + ۳ مورد اولویت‌دار بر اساس severity. داشبورد و Briefing **همیشه** کار می‌کنند.
- **نمایش:** کارت «🧠 خلاصهٔ هوشمند تصمیم‌ها» بالای داشبورد فرماندهی + خط خلاصه در ایمیل Daily Briefing.

### F-04 — MFA سوپرادمین
- **TOTP پیاده‌سازی RFC 6238** بدون کتابخانهٔ خارجی (`Totp` — hash_hmac sha1، دوره ۳۰ ثانیه، پنجره ±۱، سازگار با Google Authenticator/Authy).
- **مجدول users:** `mfa_secret`، `mfa_enabled`، `mfa_backup_codes` (json)، `mfa_enabled_at`.
- **میان‌افزار `platform.mfa`** روی گروه روت‌های پلتفرم: اگر کاربر MFA فعال دارد و در این نشست تأیید نکرده → هدایت به `platform/mfa/challenge` (روت‌های خودِ چالش مستثنا هستند).
- **جریان فعال‌سازی:** `setup` (ساخت سکرت) → نمایش سکرت + otpauth URI (لینک QR) → `enable` با کد ۶ رقمی → ۱۰ کد پشتیبان یکبارمصرف.
- **جریان ورود:** لاگین → (اگر MFA فعال) صفحهٔ چالش → کد TOTP یا کد پشتیبان → `mfa_verified` در سشن + audit.
- **غیرفعال‌سازی** فقط با کد TOTP فعلی (نه کد پشتیبان).

---

## تسک‌های اتمی

### F-01 — درگاه‌های پرداخت ✅
- [x] قرارداد `PaymentGateway` + `PaymentGatewayManager` (رجیستری + options).
- [x] درایور `ZarinpalGateway` (API v4 واقعی + sandbox بدون کلید).
- [x] درایور `AqayepardakhtGateway` (آقای پرداخت — API واقعی + sandbox).
- [x] درایور `ManualGateway` (دستی — بدون تراکنش آنلاین).
- [x] کنترلر `PlatformPaymentGatewayController` (pay + callback + verify با audit).
- [x] روت عمومی callback (بدون auth — درگاه به آن ریدایرکت می‌کند) + view نتیجه.
- [x] config: `services.zarinpal` / `services.aqayepardakht` (env).
**قبولی:** سه درایور در options باشند؛ sandbox initiate/verify کار کند؛ callback idempotent باشد.

### F-02 — پنل پیامکی ✅
- [x] قرارداد `SmsSender` + `SmsManager` (رجیستری + ثبت sms_logs).
- [x] درایور `KavenegarSms` (کاوه‌نگار — واقعی + sandbox).
- [x] migration جدول `sms_logs`.
- [x] `PlatformSmsController` (index + send با اعتبارسنجی شماره) + روت‌ها.
- [x] `Platform/Sms.vue` — فرم ارسال + تاریخچه با وضعیت.
- [x] آیتم «پنل پیامک» در ناوبری پلتفرم (۹ بخش شد).
- [x] اتصال به `DunningJob` (هشدار معوق به org) و `RemindScheduledPublishes` (یادآوری موعد) + migration `sms_reminder_sent_at`.
**قبولی:** ارسال از UI در تاریخچه ثبت شود؛ بدون کلید crash نکند؛ throttle باشد؛ هر فاکتور فقط یک‌بار پیامک معوق بگیرد.

### F-03 — خلاصهٔ هوشمند Triage ✅
- [x] `AiTriageSummary` (AI + fallback قطعی آفلاین).
- [x] اتصال به داشبورد فرماندهی (کارت «خلاصهٔ هوشمند تصمیم‌ها»).
- [x] اتصال به Daily Briefing (خط خلاصه در ایمیل + priority).
- [x] config: `services.platform_ai` (api_key/base_url/model از env).
**قبولی:** بدون کلید با fallback کار کند؛ با کلید خلاصهٔ AI برگرداند.

### F-04 — MFA سوپرادمین ✅
- [x] `Totp` (RFC 6238) — generateSecret/code/verify/backupCodes/otpauthUri.
- [x] migration ستون‌های MFA روی users + cast در User model.
- [x] میان‌افزار `platform.mfa` + رجیستر در bootstrap/app.php.
- [x] `PlatformMfaController` (index/setup/enable/disable + challenge/verify).
- [x] `Platform/Mfa.vue` (وضعیت، سکرت، QR، فعال/غیرفعال) + `Platform/MfaChallenge.vue`.
- [x] روت‌های چالش (auth ولی بدون پلتفرم) + روت‌های تنظیمات (داخل گروه پلتفرم).
- [x] **نمایش یک‌بار کدهای پشتیبان بعد از فعال‌سازی** — مودال با ۱۰ کد + دکمهٔ کپی؛ از طریق flash (session pull) فقط در همان ریدایرکت در دسترس است و بعد از رفرش دیگر نمایش داده نمی‌شود.
**قبولی:** بدون تأیید MFA دسترسی به پلتفرم بسته باشد؛ کد TOTP و کد پشتیبان کار کند؛ فعال‌سازی با audit؛ کدهای پشتیبان فقط یک‌بار نمایش داده شوند.

---

## تست‌ها

`tests/Feature/Platform/PhaseFTest.php` — ۱۱ تست / ۳۸ assert (۲ تست جدید برای اتصال SMS):

| تست | چه چیزی را تأیید می‌کند |
|---|---|
| gateway manager lists all drivers | سه درایور در رجیستری |
| zarinpal sandbox initiate and verify | initiate → pending + تراکنش؛ verify → paid |
| aqayepardakht sandbox initiate and verify | همان زنجیره برای آقای پرداخت |
| sms manager sends and logs | ارسال + ردیف در sms_logs |
| totp generates and verifies | کد ۶ رقمی + verify + کدهای پشتیبان |
| ai triage summary falls back offline | fallback قطعی با خلاصهٔ فارسی |
| mfa enable flow via http | setup → سکرت → enable با کد → ۱۰ کد پشتیبان |
| mfa challenge blocks platform until verified | بلوک بدون تأیید + دسترسی بعد از کد |
| sms panel page accessible | صفحهٔ پنل پیامک 200 |
| dunning sends sms to org phone for overdue | فاکتور معوق → پیامک + `sms_reminder_sent_at` |
| scheduled publish reminder sends sms to org phone | موعد انتشار → پیامک یادآوری |

## تأیید نهایی

- **356 تست / 1898 assert سبز** ✓ (۱۱ تست فاز F)
- typecheck ✓ · lint ✓ · build ✓ · pint ✓
- **تأیید بصری واقعی:**
  - پنل پیامک: ارسال تست از UI → «ارسال شد» در تاریخچه با شماره و متن
  - MFA: فعال‌سازی کامل با کد TOTP واقعی (سکرت → QR → enable) → **مودال ۱۰ کد پشتیبان ظاهر شد** (دکمهٔ کپی + بستن) و بعد از رفرش دیگر تکرار نشد (یک‌بارمصرف)
  - داشبورد فرماندهی با کارت جدید (بدون تصمیم pending — fallback خلاصه)
  - اتصال SMS: تست‌های DunningJob (معوق → پیامک) و RemindScheduledPublishes (موعد → پیامک) سبز

## قدم بعدی (پیشنهادی — فاز G)

- کلیدهای واقعی درگاه‌ها (env) + تست پرداخت واقعی
- اتصال SMS به DunningJob و یادآوری انتشار
- MFA برای همهٔ نقش‌های حساس (نه فقط سوپرادمین)
- کد پشتیبان MFA: صفحهٔ نمایش کدها بعد از فعال‌سازی (الان فقط در DB ذخیره می‌شود)
