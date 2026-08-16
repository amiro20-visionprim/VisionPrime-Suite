# Vision Prime SUITE — Automation Governance & Site Policy

## قانون کلان پروژه: مستندمحوری (Doc-Driven Rule) — D-020
> **قانون الزامی و غیرقابل‌مذاکره (ثبت ۲۰۲۶-۰۸-۱۶، درخواست مالک):**
> ۱) پروژه «تماماً مستندمحور» پیش می‌رود؛ هیچ تغییر مهمی (افزودن/حذف/تغییر قابلیت، migration، روت، رفتار) بدون به‌روزرسانی مستندات قبلیِ مرتبط انجام نمی‌شود.
> ۲) مستندات به‌روزرسانی‌شده باید پیش از اعلام «تمام شد» کامیت‌/ثبت شوند و با کد همگام باشند.
> ۳) هر ورودی لاگ پیشرفت (`31-Progress-Log.md`) فهرست مستندات به‌روزرسانی‌شده را ذکر می‌کند.
> ۴) لاگ پیشرفت منبع حقیقت زمان‌بندی است؛ هر تغییر مهم یک ورودی با شمارهٔ ترتیبی دارد.

## هدف
به هر سایت اجازه داده می‌شود میزان استقلال سیستم را انتخاب کند، بدون اینکه امنیت، قابلیت پاسخ‌گویی و کنترل برند از بین برود. این تنظیم، یک سوییچ ساده «دستی/خودکار» نیست؛ یک Policy قابل‌ممیزی در سطح Site است.

## اصل کلیدی
**Automation باید policy-driven باشد، نه trust-driven.**
حتی در بالاترین سطح خودکارسازی، فقط Commandهای whitelist شده، در محدوده‌های تعریف‌شده و با کنترل‌های امنیتی اجرا می‌شوند.

## 1) سطح‌های هوشمندی/عملکرد
| سطح | نام UI فارسی | رفتار | مناسب برای |
|---:|---|---|---|
| L0 | فقط مشاهده | Sync، تحلیل و گزارش؛ بدون توصیه اجرایی یا mutation | مشتری بسیار حساس یا شروع همکاری |
| L1 | پیشنهاد با تأیید کامل | سیستم توصیه/پیش‌نویس می‌سازد؛ هر مورد نیازمند تأیید انسانی است | حالت پیش‌فرض و امن |
| L2 | اجرای کنترل‌شده | تغییرات کم‌ریسکِ از پیش‌مجاز پس از تأیید یک‌باره Policy اجرا می‌شوند؛ سایر موارد تأیید می‌خواهند | تیم‌های SEO حرفه‌ای |
| L3 | خودکارسازی نظارت‌شده | تغییرات کم‌ریسک طبق Rule اجرا می‌شوند؛ سیستم اعلان، گزارش و نمونه‌برداری بازبینی دارد | عملیات پرتکرار و بالغ |
| L4 | Autopilot محدود | سیستم در budget، زمان‌بندی، نوع محتوا و دامنه مجاز تعریف‌شده خودکار عمل می‌کند؛ عملیات پرریسک همیشه ممنوع/نیازمند تأیید است | مشتریان با اعتماد بالا و فرآیند بالغ |

**L4 به معنی دسترسی نامحدود نیست.** هیچ سطحی اجازه اجرای کد دلخواه، حذف انبوه یا تغییرات خارج از لیست مجاز را نمی‌دهد.

> **اصلاحیهٔ D-013 (2026-08-14):** انتشار مستقیم محتوای AI فقط در L3 و L4 و فقط وقتی مجاز است که همگی برقرار باشند: (الف) اجازه‌نامهٔ صریح سایت (AI_policy = `bounded_auto`)، (ب) امتیاز اطمینان ≥ آستانهٔ همان سطح ریسک، (ج) rollback و snapshot فعال، (د) نوع محتوا در whitelist پروفایل. در غیر این صورت هر کدام نباشند، رفتار به حالت پیشنویس/تأیید انسانی برمی‌گردد. جزئیات در سند `34-Automation-Architecture.md`.

## 2) Risk tiers برای Commandها
| سطح ریسک | نمونه | رفتار پیش‌فرض |
|---|---|---|
| R0 اطلاع‌رسانی | Sync، تحلیل، تولید گزارش | خودکار در همه سطوح |
| R1 کم‌ریسک | اصلاح meta title/description، excerpt، ایجاد draft، پاکسازی cache امن | L2 به بالا با policy ممکن است خودکار باشد |
| R2 متوسط | ویرایش محتوای draft، افزودن internal link، تغییر CTA پیشنهادی در draft | L3 به بالا فقط با Rule و محدوده دقیق؛ در غیر این صورت Review |
| R3 پرریسک | تغییر محتوای منتشرشده، **انتشار مقاله/محصول جدید (publish_new_article)**، تغییر slug، redirect، تغییر ساختار لینک | همیشه snapshot + rollback؛ انتشار مستقیم محتوا فقط با اجازه‌نامهٔ صریح و گیت‌های scope/گرمایش/کیفیت (زیر) — در غیر این صورت تأیید انسانی |
| R4 ممنوع | اجرای کد، نصب plugin، حذف انبوه، تغییر نقش کاربر، تغییر credential | از طریق سوئیت اجرا نمی‌شود |

**دامنهٔ انتشار خودکار (`auto_publish_scope`):** انتشار مستقیم (بدون تأیید انسانی لحظه‌ای) فقط برای سایت‌هایی مجاز است که admin به‌صورت صریح opt-in کرده باشد: `none` (پیش‌فرض — همه‌چیز تأیید انسانی) | `meta` | `article` | `product` | `all`. این یک گیت fail-closed است: دامنهٔ بسته = بدون انتشار خودکار، حتی اگر پروفایل L3/L4 باشد.

**گرمایش (warm-up):** سیستم قبل از اولین انتشار خودکار در هر نوع محتوا باید اعتماد ساخته باشد — تعداد مشخصی اجرای موفقِ انسانی از همان نوع روی همین سایت: متا=۳، محصول=۳، مقاله=۵. بدون گرمایش کامل، هرچقدر امتیاز اطمینان بالا باشد، انتشار خودکار نمی‌شود.

**گیت کیفیت محتوا:** برای کامندهای محتوایی، خروجی با **استاندارد مؤثر همان پیش‌نویس** (از `content_standards` — طول، هدینگ، عناصر الزامی) ارزیابی می‌شود و نمرهٔ کیفیت در تصمیم خودکار لحاظ می‌شود؛ مقالهٔ ب‌کیفیت پایین هرچقدر GSC قوی داشته باشد نمرهٔ پایینی می‌گیرد.

## 3) ساختار Site Automation Policy
هر Site یک نسخه Policy دارد و تغییر آن audit می‌شود.

```text
SiteAutomationPolicy
- site_id
- automation_level (L0..L4)
- allowed_command_types[]
- max_risk_tier
- allowed_post_statuses[]
- allowed_post_types[]
- execution_window (timezone-aware)
- daily_command_limit
- daily_mutation_limit
- require_snapshot_for_mutations
- rollback_window_hours
- reviewer_policy (none / one / specific roles / named users)
- notification_policy
- AI_policy (disabled / draft_only / approved_templates / bounded_auto)
- auto_publish_scope (none | meta | article | product | all) ← دامنهٔ انتشار خودکار (opt-in صریح)
- emergency_stop_enabled
- active_profile_id + overrides_json (مدل سهلایهٔ D-015: Default ← Profile ← Override)
- version
- updated_by
```

## 4) Guardrails اجباری در تمام سطح‌ها
- HMAC + timestamp + nonce + replay protection (تست‌شده: nonce تکراری و timestamp منقضی رد می‌شوند)
- idempotency key برای هر Command
- expiration و cancelability
- whitelist command type و schema validation
- target validation: site، post type، post status و ownership (و `assert_product` برای محصولات)
- rate limit و daily budget
- pre-mutation snapshot در Commandهای قابل rollback (بازگشت بدون‌اتلاف، snapshot کامل)
- structured result و immutable execution log
- emergency stop در سطح Site و Organization
- notification در failure، anomaly و mutation حساس
- **گرمایش**: N اجرای موفق انسانی از همان نوع پیش از انتشار خودکار (متا=۳، محصول=۳، مقاله=۵)
- **دامنهٔ انتشار خودکار**: opt-in صریح admin (`auto_publish_scope`) — fail-closed
- **کیفیت محتوا**: ارزیابی خروجی با استاندارد مؤثر پیش‌نویس (`content_standards`) قبل از انتشار خودکار
- **تنها بودن بر دادهٔ واقعی**: گزارش تأثیر GSC هرگز عدد جعل نمی‌کند؛ بدون داده → `insufficient_data` با دلیل

## 5) UX تنظیمات Automation
مسیر: `/app/sites/{site}/automation`

### صفحه شامل
1. وضعیت فعلی با label انسانی: «پیشنهاد با تأیید کامل»
2. توضیح کوتاه “این سطح چه کار می‌کند / چه کار نمی‌کند”
3. Risk matrix قابل مشاهده
4. Command typeهای مجاز با toggle و توضیح ریسک
5. محدودیت‌های روزانه و بازه اجرای مجاز
6. تنظیم اعلان‌ها
7. لیست آخرین اجراها، نتیجه و rollback
8. دکمه قرمز اما غیرترسناک: «توقف فوری خودکارسازی»

### قواعد UX
- تغییر از سطح پایین به سطح بالاتر نیازمند modal تأیید، نمایش ریسک و ثبت دلیل است.
- L4 نیازمند تأیید Agency Admin و در صورت فعال بودن، Client Owner است.
- تغییر به سطح پایین‌تر فوراً اعمال می‌شود.
- در Portal مشتری، تنظیمات با زبان غیرتکنیکال نمایش داده می‌شود: «سطح اختیارات سامانه».

## 6) User Journey: فعال‌سازی خودکارسازی
`Site connected → Health check passes → Default L1 → user reviews suggested policy → chooses level → confirms risk summary → policy version saved → optional test command → monitoring begins`

## 7) Acceptance Criteria
- هر command در لحظه dispatch با Policy فعلی دوباره ارزیابی می‌شود.
- تغییر Policy روی commandهای قبلی اثر retroactive ندارد؛ هر اجرا policy version خودش را ذخیره می‌کند.
- Emergency stop commandهای در صف و dispatchنشده را cancel می‌کند.
- هیچ UI یا API نمی‌تواند R4 را ایجاد یا اجرا کند.
- گزارش ماهانه شامل تعداد تغییرات خودکار، موفق/ناموفق، rollback و زمان صرفه‌جویی‌شده است.
