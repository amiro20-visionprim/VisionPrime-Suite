# Vision Prime SUITE — Master Product Specification

**وضعیت:** سند مرجع محصول (Living Document)  
**هدف:** مبنای تصمیم‌گیری Product، UX/UI، Front-end، Back-end، Plugin، QA و Go-to-market  
**بازار اول:** ایران، فارسی و RTL-first؛ پشتیبانی انگلیسی به‌عنوان زبان دوم  
**نوع محصول:** Self-hosted premium SEO intelligence and WordPress growth operations platform

---

## 1) وعده محصول
ویژن پرایم سوئیت (Vision Prime SUITE) به تیم‌های SEO و آژانس‌ها کمک می‌کند تا داده‌های سایت و Google Search Console را به فرصت‌های رشدِ قابل‌فهم، توصیه‌های قابل اجرا، تغییرات کنترل‌شده و گزارش قابل ارائه به مشتری تبدیل کنند.

**حلقه ارزش محصول:**

`Connect → Collect → Analyze → Prioritize → Recommend → Approve/Automate → Execute → Measure → Report`

## 2) ICP و کاربران
### ICP اولویت اول
آژانس‌های SEO/دیجیتال مارکتینگ ایرانی با چند مشتری و چند سایت WordPress که به گزارش‌دهی، مقیاس‌پذیری عملیات، شفافیت و کنترل تغییرات نیاز دارند.

### کاربران اصلی
| کاربر | هدف اصلی | موفقیت برای او |
|---|---|---|
| Agency Admin | مدیریت مشتریان، ریسک و خروجی تیم | پروژه‌ها کنترل‌شده و سودده باشند |
| SEO Manager | کشف و اولویت‌بندی فرصت | سریع به اقدام با بیشترین اثر برسد |
| Content Manager | تولید/اصلاح محتوا | brief و توصیه قابل استفاده داشته باشد |
| Reviewer | کنترل کیفیت و ریسک | فقط تغییرات درست و امن تصویب شوند |
| Developer | اجرای فنی و اتصال WordPress | تغییرات قابل ردیابی و برگشت‌پذیر باشند |
| Client Viewer | دیدن نتیجه و تصمیم‌گیری | بفهمد چه شده، چه نتیجه‌ای داشته و چه چیزی نیاز به تأیید دارد |

## 3) اصول قطعی محصول
1. **RTL و فارسی First-class هستند**؛ نه یک ترجمه روی UI انگلیسی.
2. تاریخ در دیتابیس UTC/Gregorian ذخیره می‌شود؛ نمایش UI از جلالی پشتیبانی می‌کند.
3. تمام صفحات mixed-direction را درست نمایش می‌دهند: متن فارسی RTL؛ URL، Query، کد و مقادیر فنی LTR.
4. هر Insight باید منبع داده، زمان آخرین Sync، سطح اطمینان و منطق/فاکتورهای اثرگذار داشته باشد.
5. هیچ تغییر پرریسکی بدون سیاست مجاز، لاگ، قابلیت توقف و در صورت امکان rollback اجرا نمی‌شود.
6. AI یک copilot کنترل‌شده است؛ کلید AI فقط در Laravel نگه‌داری می‌شود.
7. Client Portal نتیجه‌محور است؛ جزئیات فنی فقط در صورت نیاز نمایش داده می‌شوند.
8. همه عملیات حساس audit trail دارند.
9. پلتفرم Modular Monolith است، نه Microservice.

## 4) ستون‌های محصول
1. Marketing + Conversion Landing Pages
2. Identity, RBAC, Organization, Client, Project, Site
3. WordPress Secure Connector
4. Content Sync + URL Profiles
5. Google Search Console Integration
6. SEO Intelligence: intent, mapping, opportunities, cannibalization
7. Money Page Optimizer + Conversion Risk
8. AI Gateway + Review Workflow — ✅ فعال: تولید پیش‌نویس مقاله/محصول با استاندارد مؤثر (`content_standards`)، تصویر شاخص، اسکیمای Schema.org و HTML پاک‌سازی‌شده
9. Policy-driven Secure Commands + Rollback — ✅ فعال: شش نوع command، انتشار خودکار با گیت‌های scope/گرمایش/کیفیت (D-017)، rollback بدون‌اتلاف
10. Client Portal, Reporting and Impact Tracking — ✅ گزارش تأثیر پس از انتشار با دادهٔ واقعی GSC (D-019) + کارت «تأثیر محتوا» در داشبورد

**حلقهٔ ارزش (تکمیل‌شده):** `Connect → Collect → Analyze → Prioritize → Recommend → Approve/Automate → Execute → Measure → Report` — حلقهٔ «Measure» حالا با مقایسهٔ GSC قبل/بعد انتشار واقعی است.

## 5) معیارهای کیفی انتشار
هر قابلیت پیش از Done شدن باید این موارد را داشته باشد:
- Acceptance criteria و تست مسیر موفق/خطا
- مجوز دسترسی و Policy مشخص
- حالت empty/loading/error/success
- RTL، موبایل و keyboard basics بررسی شده
- Audit برای عملیات حساس
- متن‌های فارسی محصولی و قابل فهم
- Event tracking مورد نیاز
- عدم وجود secret در frontend، log یا plugin

## 6) Non-goals
- اجرای کد دلخواه روی WordPress
- انتشار مستقیم خروجی AI خارج از Policy سایت (مسیر مجازِ دارای اجازه‌نامه با گیت‌های D-017 مستثنی است — رجوع به سند ۰۱)
- ارائه تشخیص SEO به‌عنوان تضمین رتبه یا درآمد
- نشان‌دادن داده خام و noisy به Client Viewer

## 7) تصمیم‌های باز که باید قبل از توسعه مربوطه ثبت شوند
- مدل استقرار: SaaS، private cloud یا self-hosted هر مشتری
- Providerهای AI و روش پرداخت/دسترسی بازار ایران
- سطح دقیق دسترسی plugin و پشتیبانی از multisite
- export گزارش: PDF، لینک امن، ایمیل یا همه
- مدل قیمت‌گذاری: per site / per seat / per agency / usage based

> هر تصمیم باید در `04-Decision-Log.md` همراه با تاریخ، مالک تصمیم و دلیل ثبت شود.
