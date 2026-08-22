# Changelog — Vision Prime SUITE

## [2.3.0] — 2026-08-23

### 🧠 هوش داخلی اکوسیستم سوئیت — پرامپت V2.0

#### Added
- **قالب پرامپت مقاله** (`suite-intelligence-v2`) — Anti-AI Detection، E-E-A-T، CTA/Micro-CTA، Featured Snippet
- **قالب پامپت محصول** (`suite-intelligence-v2-product`) — Domain Expertise Engine، لحن تطبیقی ۸ حوزه، ۳ بلوک خروجی
- **Meta Description خودکار** — استخراج از ۱۵۵ کاراکتر اول محتوا
- **Micro-CTA در user prompt** — حداقل ۱ CTA غیرمستقیم در میانه مقاله
- **Featured Snippet strategy** — پاراگراف پاسخ مستقیم ۴۰-۵۰ کلمه‌ای

#### Fixed
- **GapGPT max_tokens**: 1000 → 8000 (طبق مستندات رسمی gapgpt-qwen-3.6)
- **callOpenAiCompatible**: استفاده از `withBody()` به جای array مستقیم (Guzzle form-encode bug)
- **callAnthropic**: حذف متغیر undefined `$provider`
- **custom_instructions**: کنترلر ارسال می‌کرد ولی AiGateway نمی‌خواند
- **word_count**: کنترلر ارسال می‌کرد ولی نادیده گرفته می‌شد
- **tone override**: اکنون انتخاب کاربر مقدم بر guardrails
- **Permission seeding**: صفحات سایت‌ها و سازمان 403 بودند → RolePermissionSeeder اجرا شد
- **RuleBased fallback**: custom_instructions + internal_links اکنون در fallback هم اعمال می‌شوند
- **ProductDraft**: فیلدهای custom_prompt، tone، word_count به API ارسال می‌شوند

#### Changed
- **System prompt بازنویسی شد**: اضافه شدن Anti-AI Detection، E-E-A-T، Conversion، Featured Snippet
- **User prompt تقویت شد**: Micro-CTA، Featured Snippet paragraph، mobile-friendly table

### 📊 نتایج تست
| متریک | قبل | بعد |
|-------|------|-----|
| طول محتوا | 2,641 | 8,513 (+223%) |
| H2 | 3 | 8 |
| Bold | 0 | 13 |
| Links | 1 | 7 |
| FAQ | ❌ | ✅ |
| CTA | ❌ | ✅ |

---

## [2.2.0] — 2026-08-22

### Added
- **InternalLinkEngine** — ۷۶ url_profile از liuna.ir برای لینک‌سازی داخلی
- **ContentGuardrail** — جدول و مدل برای مدیریت قوانین محتوا
- **Prompt Templates** — قالب‌های آماده برای انواع محتوا
- **SERP Intelligence** — تحلیل ساختار محتوای رقبا

### Fixed
- **ReadabilityService**: باگ `];` به جای `};`
- **Outline generation**: بهینه‌سازی پرامپت outline با GapGPT

---

## [2.1.0] — 2026-08-20

### Added
- **WordPress Connector** — اتصال liuna.ir از طریق REST API
- **Content Sync** — سینک صفحات، مقالات، دسته‌بندی‌ها و برچسب‌ها
- **Draft List** — لیست پیش‌نویس‌ها با فیلتر و جستجو
- **WordPress Publish** — انتشار مستقیم از سیستم به وردپرس

---

## [2.0.0] — 2026-08-15

### Added
- **AI Gateway** — سیستم failover خودکار بین AI providers
- **Article Draft** — صفحه تولید مقاله با outline + generate
- **Product Draft** — صفحه تولید محصول
- **Quality Score** — امتیازدهی خودکار محتوا
- **GSC Integration** — اتصال Google Search Console

---

*این فایل با هر تغییر مهم بروزرسانی می‌شود.*
