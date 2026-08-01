# Vision Prime — End-to-End User Journeys

## استاندارد نوشتن هر Journey
برای هر Flow جدید باید: Persona، Trigger، Goal، Preconditions، مراحل، حالت‌های خطا، Eventها، معیار موفقیت و Acceptance Criteria ثبت شود.

---

## J1 — Agency Admin: شروع و ساخت اولین سایت
**هدف:** ایجاد یک Site آماده برای تحلیل بدون سردرگمی.

| مرحله | تجربه کاربر | پاسخ سیستم |
|---|---|---|
| 1 | ورود/ثبت‌نام | خوش‌آمدگویی و CTA «افزودن اولین سایت» |
| 2 | ساخت Client | اعتبارسنجی نام و نمایش توضیح کوتاه |
| 3 | ساخت Project | انتخاب مشتری و هدف پروژه |
| 4 | افزودن Site URL | canonical URL validation و تشخیص URL تکراری |
| 5 | انتخاب اتصال‌ها | GSC ضروری برای performance insight؛ WordPress توصیه‌شده برای تحلیل و اجرا |
| 6 | اتصال GSC | OAuth، انتخاب property، confirmation واضح |
| 7 | شروع import | progress screen، زمان تقریبی، امکان خروج از صفحه |
| 8 | مشاهده اولین نتیجه | 3 فرصت اولویت‌دار + وضعیت داده + CTA «مشاهده تحلیل کامل» |

**خطاهای مهم:** OAuth ناموفق، property پیدا نشد، URL mismatch، import failure، WordPress REST API unavailable.

**معیار موفقیت:** سایت متصل، اولین import کامل، حداقل یک Opportunity قابل‌نمایش.

---

## J2 — SEO Manager: از Opportunity تا اقدام
**هدف:** تبدیل داده به اقدام قابل‌پیگیری.

`Opportunities list → filter/sort → opportunity detail → evidence and factors → recommendation → review/approval or policy execution → impact tracking`

### Opportunity detail باید جواب دهد
- چرا این مورد مهم است؟
- داده از کجاست و مربوط به چه بازه‌ای است؟
- چه مقدار پتانسیل دارد و confidence چقدر است؟
- اقدام پیشنهادی چیست؟
- آیا تغییر مشابه قبلاً انجام شده است؟
- برای اجرا نیاز به چه کسی/چه تأییدی داریم؟

**موفقیت:** Recommendation با owner، deadline و وضعیت روشن ساخته شود.

---

## J3 — Reviewer: بررسی و تأیید
**هدف:** تصمیم سریع، قابل دفاع و قابل ممیزی.

`Review queue → item detail → compare current/proposed → inspect evidence → approve/reject/request changes → note required → audit recorded`

**قاعده:** Reviewer هرگز نباید برای فهم تغییر پیشنهادی مجبور به خروج از صفحه Review شود.

**برای AI output:** نسخه‌ها قابل مقایسه‌اند و دلیل/منبع پیشنهاد نمایش داده می‌شود.

---

## J4 — Automation: اجرای Policy-driven
**هدف:** اجرای بدون اصطکاک در محدوده مجاز.

`eligible recommendation → policy evaluation → snapshot → signed command → plugin validation → execution → result → monitoring/notification → optional rollback`

**حالت‌های توقف:** policy denied، command expired، health degraded، rate limit، emergency stop، plugin validation failed.

**قاعده UX:** کاربر باید بداند «چرا اجرا شد» یا «چرا اجرا نشد»؛ نه فقط یک status مبهم.

---

## J5 — Client Viewer: فهم نتیجه و تصمیم‌گیری
**هدف:** کارفرما در کمتر از 60 ثانیه وضعیت را بفهمد.

صفحه `/client/dashboard` به این ترتیب:
1. خلاصه وضعیت و روند نسبت به دوره قبل
2. سه فرصت/اولویت اصلی با زبان کسب‌وکاری
3. کارهای انجام‌شده و اثر مشاهده‌شده
4. آیتم‌های «نیازمند تصمیم شما»
5. گزارش آخر و گام بعدی

**اجتناب شود از:** جدول خام query، فاکتورهای فنی متعدد، command logs، اصطلاحات داخلی SEO.

---

## J6 — Monthly Reporting
`select site/date/modules → data validation → preview → executive narrative → publish secure report → client view → client acknowledgement → archive`

**بخش‌های گزارش:** خلاصه مدیریتی، روند visibility، اقدامات، اثر مشاهده‌شده با attribution محتاطانه، اولویت ماه آینده، تصمیم موردنیاز مشتری، ضمیمه فنی اختیاری.

---

## Event Tracking اولیه
- `site_created`
- `gsc_connected`, `gsc_import_started`, `gsc_import_completed`, `gsc_import_failed`
- `connector_paired`, `connector_health_failed`
- `opportunity_viewed`, `recommendation_created`
- `review_approved`, `review_rejected`
- `command_dispatched`, `command_executed`, `command_failed`, `rollback_started`
- `report_generated`, `report_viewed`
- `automation_policy_changed`, `emergency_stop_activated`
