# 30 — Gap Audit: Fixes Delivered & Deferred Items

تاریخ: ۲۰۲۶-۰۸-۰۶ — پس از ریویو صفر تا صد با تمرکز بر جورنی کاربر (J1/J2)، موارد زیر اصلاح شد و موارد باقی‌مانده آگاهانه defer شد.

## ✅ رفع‌های انجام‌شده (P0 / P1)

### فانل ورود (J1)
- **ثبت‌نام کامل**: روت‌های `GET/POST /register`، `RegisterController`، `RegisterRequest` (اعتبارسنجی + rate limit `throttle:10,1`)، صفحه `Auth/Register.vue`.
- مسیر بعد از ثبت‌نام: آنبوردینگ (`/app/onboarding`) → ساخت سازمان → داشبورد. (قبل از این، فانل سلف‌سرویس بسته بود.)
- CTAهای «ساخت حساب رایگان / شروع رایگان» در هدر مارکتینگ، صفحه اصلی و لاگین.

### باگ داشبورد (crash)
- `DashboardController` پراپ `activities` را از `ProjectDashboardActivity::forOrganization` پاس می‌دهد (قبلاً تعریف‌نشده بود و Vue کرش می‌کرد).
- شمارش `connectedSites` (از `site_connections`) و `openOpportunities` (از `opportunities` با status=open) واقعی شد.

### جورنی J2 (فرصت → اقدام)
- **پیشنهادها (Recommendations)**: مدل `Recommendation`، کنترلر با index/create/store/update، روت‌های GET/POST/PUT، صفحات Index (لیست + مدال ویرایش مالک/مهلت/اولویت/وضعیت) و Create.
- نگاشت صریح camelCase در کنترلر (کنوانسیون پروژه — بدون اتکا به camelize خودکار).
- `owner_id` فقط از اعضای فعال همان سازمان معتبر است.
- **تبدیل فرصت به پیشنهاد**: `POST /app/opportunities/{id}/recommendation` + CTA در صفحه جزئیات فرصت.
- صفحه جزئیات فرصت غنی شد: نوع، وضعیت، سایت، URL، کلیدواژه، تاریخ شناسایی، پتانسیل (امتیاز/اطمینان) و تحلیل عوامل با برچسب فارسی.

### جذب لید (مارکتینگ)
- فرم `/demo` واقعی شد: جدول `leads` + مدل `Lead` + `LeadController` (rate limit `throttle:10,1`) + نمایش خطا/موفقیت.

### پرتال مشتری
- صفحه «رشد و فرصت‌ها» (`/client/growth`) با داده واقعی `ClientGrowthSummary` + فرصت‌های باز مرتبط.
- **چهار صفحهٔ باقی‌مانده واقعی شدند** (جایگزینی `PortalPlaceholder`):
  - **سلامت سایت** (`/client/site-health`) — `ClientSiteHealthController`: وضعیت اتصال (`site_connections`)، آخرین امتیاز سلامت (`money_page_audits`)، مشکلات (`money_page_issues`)، ریسک‌های تبدیل (`conversion_risks`) و شمار صفحات (`url_profiles`) به‌ازای هر سایت + خلاصهٔ کل/متصل/نیازمند توجه.
  - **اولویت‌ها** (`/client/opportunities`) — `ClientPrioritiesController`: فرصت‌های باز مرتب‌شده با امتیاز/اطمینان + پیشنهادهای فعال با مالک و مهلت.
  - **نیازمند تصمیم شما** (`/client/decisions`) — `ClientDecisionsController`: دستورهای `pending_approval` (با مهلت تصمیم) و بازبینی‌های `pending_review`.
  - **فعالیت‌ها** (`/client/activity`) — `ClientActivityController`: تایم‌لاین آخرین `audit_logs` سازمان با برچسب فارسی و نام بازیگر.
- همهٔ صفحات از دامنه‌های واقعی کوئری می‌گیرند (بدون hardcode) و تاریخ‌ها با `formatJalaliDate` نمایش داده می‌شوند.

### کیفیت UI
- فارسی‌سازی برچسب‌های انگلیسی: صفحات GSC (جستار/کلیک/نمایش/نرخ کلیک/جایگاه)، Commands، Reviews، UrlProfiles، Sites (Sync/Connector)، Reports، SiteForm.

### تست
- +۱۶ تست Feature: ثبت‌نام (۴)، داشبورد (۲)، پیشنهادها (۶)، صفحات پرتال مشتری (۴). مجموع: ۷۹ تست، ۲۹۷ assertion، همگی سبز + typecheck و lint تمیز.

## ⏳ موارد defer شده (با دلیل)

| مورد | دلیل | پیشنهاد بعدی |
|---|---|---|
| **Endpoint اجرای command در پلاگین وردپرس** | نیاز به آزمون روی یک سایت وردپرسی واقعی دارد؛ اجرای کد از راه دور ریسک عملیاتی دارد و بدون تست میدانی قابل تضمین نیست | توسعه در فاز مجزای «Automation Execution» با تست میدانی روی staging وردپرس |
| **راه‌حل GSC برای ایران** | به تصمیم محصول نیاز دارد: (الف) توضیح کاربرد VPN/سرور خارجی، (ب) پراکسی API، (ج) منبع داده جایگزین (Bing Webmaster/Ahrefs) | تصمیم در Decision Log (مرتبط با O-001) + طراحی fallback داده |
| **فلو 2FA** | ستون‌های migrations موجود است ولی نیاز به انتخاب کانال ارسال (ایمیل/SMS ایرانی) و پیکربندی provider دارد | انتخاب provider + پیاده‌سازی در فاز Security |
| **Event tracking فانل مارکتینگ** | نیاز به انتخاب ابزار آنالیتیک (Plausible/Matomo و…) دارد | افزودن پس از راه‌اندازی دامنهٔ پروداکشن |

## پیمانه‌های باز در Decision Log
O-001 تا O-005 (منبع داده ایران، سیاست خودکارسازی، مدل قیمت‌گذاری، حریم دادهٔ مشتری، کانال تأیید) — بدون مالک و تاریخ. پیشنهاد: مالک/تاریخ در جلسهٔ بعدی تعیین شود.
