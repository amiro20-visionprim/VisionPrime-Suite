# 34 — معماری اتوماسیون سرتاسری AI (D-013)

**تاریخ:** ۲۰۲۶-۰۸-۱۴
**وضعیت:** تصویب‌شده توسط مالک (پاسخ به ۵ سؤال مشورت) — مبنای اجرای فازی
**مبنای حاکمیتی:** `01-Automation-Governance-and-Site-Policy.md`، تصمیمات D-005 (سطوح L0–L4)، D-006 (بازتعریف‌شده)، D-008 (R3 فقط Enterprise)، D-013

> **تعریف D-013:** «از اتصال تا تحلیل و انتشار، همه‌چیز اتوماسیون از روی داده‌های تصمیم‌ساز» — با اجازه‌نامهٔ صریح، امتیاز اطمینان، rollback و audit کامل. این سند D-006 («AI هرگز مستقیم publish نمی‌کند») را برای مسیر مجازِ دارای اجازه‌نامه بازتعریف می‌کند.

---

## ۱) تصمیمات مالک (پاسخ به ۵ سؤال)

| # | سؤال | تصمیم |
|---|---|---|
| 1 | سطح پیش‌فرض | **L1** (پیشنهاد با تأیید کامل) — ارتقای تدریجی به L2/L3/L4 با تأیید |
| 2 | آستانهٔ انتشار پیش‌فرض | **۸۰٪** امتیاز اطمینان برای انتشار خودکار R1؛ R2/R3 آستانهٔ بالاتر (۹۰٪+) و قواعد سخت‌گیرانه‌تر |
| 3 | بازگشت | **R3: خودکار** (با notification)؛ **R1: هشدار**؛ همیشه با پنجرهٔ rollback و snapshot |
| 4 | دامنهٔ خودکارسازی | **کامل:** متا + محتوای متنی/مقالات + محصولات — همه با تنظیمات گسترده و چندین پروفایل |
| 5 | فازبندی | ۴ فاز اتمی (پایین، همین سند) |

**اصل کلیدی اضافه‌شده توسط مالک:** جدا از تنظیمات پیش‌فرض و پروفایل‌های از پیش‌تعیین‌شده، **همه‌چیز در داشبورد قابل تنظیم** است (Default → Profile → Per-site override)، و **چندین پروفایل** هم‌زمان برای یک سایت قابل تعریف است (مثلاً پروفایل «مقالات» و پروفایل «متا» با سطح/آستانهٔ متفاوت).

---

## ۲) اصلاحیهٔ سند ۰۱ (به‌موجب D-013)

بند «هیچ سطحی اجازهٔ ... انتشار مستقیم محتوای AI را نمی‌دهد» به این شکل اصلاح می‌شود:

> انتشار مستقیم محتوای AI **فقط در L3 و L4** و فقط وقتی مجاز است که همگی برقرار باشند: (الف) اجازه‌نامهٔ صریح سایت (AI_policy = `bounded_auto`)، (ب) امتیاز اطمینان ≥ آستانهٔ همان سطح ریسک، (ج) rollback و snapshot فعال، (د) نوع محتوا در whitelist پروفایل. در غیر این صورت (الف)–(د) هر کدام نباشند، رفتار به حالت قبلی (draft/tamneed) برمی‌گردد.

- **R2** (ویرایش محتوای draft، internal link، CTA پیشنهادی): L3 به بالا با Rule و محدودهٔ دقیق خودکار؛ بقیه Review.
- **R3** (تغییر محتوای منتشرشده، مقالهٔ جدید زنده): **فقط Enterprise** (D-008) + تأیید یک‌بارهٔ Policy + snapshot + rollback خودکار. انتشار مقالهٔ جدید زنده معادل R3 رفتار می‌شود.

---

## ۳) مدل پیکربندی سهلایه (Default → Profile → Override)

```
L1: پیش‌فرض‌های سیستم (hardcoded safe defaults)   ← همیشه امن، نقطهٔ شروع
  └─ L2: پروفایل‌های آماده (system presets)        ← «شروع امن» L1 / «رشد متعادل» L2 / «خودکار نظارت‌شده» L3 / «Autopilot محدود» L4
       └─ L3: شخصی‌سازی per-site در داشبورد         ← هر پارامتر قابل تغییر + چند پروفایل هم‌زمان
```

**جدول پیشنهادی `automation_profiles`** (جدید):

| فیلد | توضیح |
|---|---|
| id / name / slug | شناسه و نام نمایشی |
| kind | `system` (آماده، غیرقابل حذف) یا `custom` |
| scope | `org` (مشترک) یا `site` (اختصاصی) |
| automation_level | L0..L4 |
| ai_policy | disabled / draft_only / approved_templates / bounded_auto |
| confidence_threshold | آستانهٔ انتشار (مثلاً ۸۰) + آستانهٔ R2/R3 (۹۰) |
| enabled_content_types | meta[]، article[]، product[] |
| risk_tier_max | حداکثر سطح ریسک خودکار |
| caps | daily_command_limit، daily_mutation_limit |
| execution_window | بازهٔ اجرا (timezone-aware) + blackout |
| rollback | rollback_window_hours، auto_rollback (R3)، alert_level |
| reviewer_policy | none / one / specific roles / named users |
| notification_policy | کانال و رویدادها |
| is_active | فعال بودن روی سایت |
| version / updated_by | نسخه و ممیز |

`site_automation_policies` (موجود) به‌جای `level` ساده به `active_profile_id` + `overrides_json` اشاره می‌کند (با backward-compat برای level).

---

## ۴) دامنهٔ محتوا و Command types

| ContentType | Command types | ریسک | سطح خودکارسازی مجاز | وضعیت اجرا |
|---|---|---|---|---|
| meta | update_meta_title، update_meta_description | R1 | L2+ با policy؛ R1 آستانه ۸۰٪ | ✅ پیاده‌شده (موجود) |
| product | update_product_title، update_product_description | R1/R2 | R1: L2+؛ R2: L3+ با Rule | ✅ پیاده‌شده (با assert_product) |
| article | update_content (ویرایش محتوای موجود) | R2 | L3+ با Rule و محدوده | ✅ پیاده‌شده (از UI پیشنهادها هم قابل‌ساخت است) |
| article | publish_new_article (مقاله/محصول جدید زنده) | R3 | فقط با اجازه‌نامهٔ صریح (bounded_auto) + گیت‌های scope/گرمایش/کیفیت + snapshot + rollback | ✅ پیاده‌شده (فاز ۵) |
| article | update_published_content | R3 | همان R3 بالا | ⏳ در برنامه (ساختار آماده) |

پلاگین وردپرس: نوع‌های `update_meta_title` / `update_meta_description` / `update_content` / `update_product_title` / `update_product_description` / `publish_new_article` با schema و whitelist دقیق + `assert_product` برای محصولات + endpoint های `/rollback` و `/product-info` (فایل‌های `vision-prime-wordpress-plugin`).

**دامنهٔ انتشار خودکار (`site_automation_policies.auto_publish_scope`):** برای سایت‌های کم‌حساسیت، admin با opt-in صریح دامنه را تعیین می‌کند: `none` (پیش‌فرض) | `meta` | `article` | `product` | `all`. بدون دامنهٔ باز، هیچ انتشار خودکاری حتی با شرایط مساعد انجام نمی‌شود (fail-closed).

---

## ۵) خط لولهٔ ۷ مرحله‌ای (یکپارچه با موجود)

```
دادهٔ GSC → (1) تحلیل/فرصت (Seo Domain: موجود)
  → (2) پیش‌نویس (RuleBasedDraft / AiClient، source=rule_based|ai)
  → (3) امتیاز اطمینان (ConfidenceScorer — جدید)
  → (4) تصمیم: PolicyEvaluator (L0–L4 + AI_policy + آستانه + caps + پنجره + blackout)
  → (5) انتشار خودکار (AutoPublish) یا pending_approval (موجود)
  → (6) اجرا روی وردپرس (پلاگین HMAC موجود + انواع جدید)
  → (7) سنجش اثر (impact_events موجود) + بازگشت خودکار (R3)
       ↓ (حلقهٔ یادگیری) به‌روزرسانی امتیاز اطمینان و اولویت برای دفعهٔ بعد
```

هر قدم audit می‌شود؛ هر اجرا `policy_version` و snapshot خودش را ذخیره می‌کند (شرط ۰۱).

---

## ۶) امتیاز اطمینان (`confidence_score` روی commands)

`confidence_score = w1·کیفیت_داده + w2·قدرت_سیگنال + w3·اتفاق‌نظر_منابع + w4·سابقهٔ_موفقیت`

- **کیفیت دادهٔ GSC:** حجم جستجو، تازگی همگام، پوشش صفحه
- **قدرت سیگنال:** اندازهٔ شکاف CTR، رتبهٔ فعلی، intent و ارزش تجاری (از ScoreRevenueOpportunities موجود)
- **اتفاق‌نظر منابع:** rule_based و ai هم‌نظر باشند وزن بیشتر؛ اختلاف → کاهش اطمینان
- **سابقهٔ موفقیت:** از impact_events برای همان نوع تغییر/همان سایت/سایت‌های مشابه
- خروجی ۰–۱۰۰؛ ذخیره در `commands.confidence_score` + `confidence_factors` (JSON، قابل بازبینی)

آستانه‌ها در پروفایل: R1=۸۰، R2/R3=۹۰ (قابل تغییر در داشبورد).

---

## ۷) رفتار تصمیم‌گیری (PolicyEvaluator)

| وضعیت | رفتار |
|---|---|
| زیر L2 یا ai_policy != bounded_auto | همیشه `pending_approval` (وضعیت فعلی) |
| L2 و R1 و اطمینان ≥ آستانه | AutoPublish با reviewer=system + policy_snapshot |
| L3 و R1/R2 (در whitelist) و اطمینان ≥ آستانه | AutoPublish + نمونه‌برداری بازبینی (نمونه‌ای از اجراها برای بررسی انسانی) |
| L4 | همان L3 + budget/زمان‌بندی/نوع محتوا از پروفایل |
| R3 | فقط Enterprise + تأیید صریح Policy + snapshot + rollback خودکار |
| خارج از caps / پنجره / blackout | به صف فردای مجاز می‌رود یا تأخیر |
| emergency_stopped_at فعال | cancel دستورهای در صف؛ هیچ dispatch جدیدی |

---

## ۸) بازگشت و ایمنی (غیرقابل مذاکره)

- snapshot قبل از هر mutation (rollback_snapshots موجود) — الزامی برای R2/R3
- **R3:** بازگشت خودکار اگر در پنجرهٔ سنجش (مثلاً ۷–۱۴ روز) بازدید/CTR زیر baseline افت کرد (baseline از impact_events)
- **R1:** هشدار؛ بازگشت پیشنهادی
- سقف هفتگی تغییرات + پنجرهٔ اجرا (مثلاً خارج از ساعت اوج فروش) از پروفایل
- توقف اضطراری سایت/سازمان؛ L4 فقط با تأیید Agency Admin (+ Client Owner در صورت فعال بودن) — از ۰۱

---

## ۹) حلقهٔ یادگیری

Job دوره‌ای: impact_events → برای هر (نوع تغییر × سایت) امتیاز موفقیت → به‌روزرسانی `confidence_factors.history` → تنظیم اولویت فرصت‌های آینده. بدون این حلقه، امتیاز اطمینان فقط حدس است.

---

## ۱۰) داشبورد

**مسیر:** `/app/sites/{site}/automation` (از ۰۱) + صفحهٔ جدید «اعتماد به سیستم»

1. **ویرایشگر پروفایل:** ساخت/ویرایش/کپی/حذف پروفایل؛ انتخاب سطح (L0–L4 با label انسانی و «چیست/چه نیست»)؛ AI_policy؛ آستانه‌ها (اسلایدر)؛ toggle انواع محتوا (meta/article/product) با برچسب ریسک؛ caps؛ پنجرهٔ اجرا؛ blackout
2. **فعال‌سازی چند پروفایل:** مثلاً «مقالات (L3، ۹۰٪)» + «متا (L2، ۸۰٪)» — مسیریابی بر اساس ContentType
3. **توقف اضطراری:** دکمهٔ قرمز غیرترسناک + cancel در صف
4. **اعتماد به سیستم:** نرخ موفقیت انتشار خودکار، rollbackها، تعداد تأییدشده/خودکار، زمان صرفه‌جویی‌شده، نمونه‌برداری بازبینی، لاگ اجراها با policy_version
5. تغییر سطح بالاتر: modal تأیید + نمایش ریسک + ثبت دلیل (از ۰۱)
6. **تولید پیشنویس:** صفحات «تولید پیشنویس مقاله» (`/app/ai-drafts/article/create`) و «تولید پیشنویس محصول» (`/app/ai-drafts/product/create`) — انتخاب سایت/پروفایل/زیرنوع، نمایش استاندارد مؤثر، پیش‌نمایش HTML پاک‌سازی‌شده + اسکیمای پیشنهادی، و ارسال به صف بازبینی
7. **کارت «تأثیر محتوا» در داشبورد:** خلاصهٔ گزارش تأثیر همهٔ کامندهای publish اجراشده (تعداد، توزیع وضعیت، بهترین/ضعیف‌ترین بهبود، فهرست افت‌ها) — فقط دادهٔ واقعی GSC
8. **تقویم محتوایی (`/app/content-calendar`):** شبکهٔ ماهانه/هفتگی جلالی برای زمان‌بندی انتشار پیش‌نویس‌های مقاله/محصول — زمان‌بندی/تغییر زمان/لغو/**انتشار فوری** (`POST /app/content-calendar/commands/{command}/schedule`)، **دراگ‌اندروپ برای جابجایی پیش‌نویس بین روزها**، فیلتر سایت، ساخت پیش‌نویس زمان‌بندی‌شده از داخل تقویم (`POST /app/content-calendar/drafts`)، پیشنهاد هوشمند **روز و ساعت** انتشار از GSC (روزانه + ساعتی)، **یادآوری موعد انتشار** (روزانه ۰۹:۰۰، یک روز قبل)، و اتصال کامل به pipeline: کامندهای `status=scheduled` در موعد توسط `ReleaseScheduledCommands` (هر دقیقه) آزاد و از AutoPublish عبور می‌کنند. تأیید پیش‌نویس با `scheduled_for` (روی خود پیش‌نویس یا پارامتر تأیید) در جریان بازبینی هم زمان‌بندی مستقیم می‌کند.

---

## ۱۱) فازبندی اتمی اجرا

### فاز ۱ — پایهٔ داده و موتور امتیازدهی
- [x] 1-1: migration — `commands` + confidence_score، confidence_factors، decision_source (policy/manual)، published_at
- [x] 1-2: migration — `automation_profiles` + seed پروفایل‌های آماده (شروع امن L1 / رشد متعادل L2 / خودکار نظارت‌شده L3 / Autopilot محدود L4)
- [x] 1-3: migration — `site_automation_policies` + active_profile_id + overrides_json (با backward-compat)
- [x] 1-4: `ConfidenceScorer` (کیفیت داده + سیگنال + اتفاق‌نظر + سابقه) + تست واحد
- [x] 1-5: پلاگین — Command types جدید (update_content، update_product_*) + schema validation
- [ ] 1-6: تست Feature: ذخیره/بازیابی پروفایل و امتیاز

### فاز ۲ — موتور تصمیم و انتشار خودکار
- [x] 2-1: `PolicyEvaluator` (L0–L4 + AI_policy + آستانه + caps + پنجره + blackout + fallback legacy)
- [x] 2-2: `AutoPublish` — عبور از pending_approval وقتی Policy مجاز است؛ reviewer=system؛ policy_snapshot؛ decision_source=policy؛ published_at
- [x] 2-3: توقف اضطراری (EmergencyStop/ResumeAutomation) + cancel دستورهای در صف + گارد re-eval در DispatchCommand
- [x] 2-4: داشبورد — ویرایشگر پروفایل + سیاست سایت (انتخاب L0–L4 با label انسانی، AI_policy، آستانه، caps، toggle محتوا، توقف/رفع اضطراری، آخرین اجراها با policy_version) — مسیر `/app/sites/{site}/automation`
- [x] 2-5: تست‌ها (۱۴ واحد + ۱۴ Feature) + Acceptance Criteria سند ۰۱ (re-eval لحظهٔ dispatch ✓، emergency stop ✓، R4 ممنوع ✓)

### فاز ۳ — حلقهٔ یادگیری و بازگشت خودکار
- [x] 3-1: `LearningLoop` job — نرخ موفقیت هر (نوع تغییر × سایت) از وضعیت واقعی اجراها → جدول `automation_learning_history` (پایهٔ `confidence_factors.history`)
- [x] 3-2: بازگشت خودکار R3 — endpoint رولبک پلاگین + `RollbackCommand` + `RollbackMonitor` (افت ≥۲۰٪ کلیک زیر baseline هفت‌روزه → rollback؛ snapshot کامل محتوا برای بازگشت بدون‌اتلاف)
- [x] 3-2b: **هشدار R1** — `RecordMetricDropAlert` + `AutomationAlert`؛ افت R1 فقط هشدار (بدون rollback)، رویداد ممیزی با dedupe ۲۴ ساعته + اعلان به اعضای سازمان با توجه به `alert_level`/`notification_policy` پروفایل
- [x] 3-3: داشبورد «اعتماد به سیستم» — `/app/sites/{site}/automation/trust`: نرخ موفقیت انتشار خودکار، تأییدشده/خودکار (system vs user)، rollbackها، زمان صرفه‌جویی‌شده (تخمینی)، نرخ موفقیت هر نوع تغییر (حلقهٔ یادگیری)، نمونهٔ انتشارهای خودکار برای بازبینی
- [x] 3-4: تست‌ها (LearningLoop، RollbackCommand، RollbackMonitor + هشدار R1 و dedupe، CommandConfidenceAssessor)

### اتصال موتور امتیاز به خط لوله
- [x] 3-5: `CommandConfidenceAssessor` — سیگنال‌ها: تازگی همگام GSC، confidence فرصت مبدأ، توافق منابع (rule_based/ai)، سابقهٔ یادگیری → ثبت `confidence_score` + `confidence_factors` روی command در ساخت از توصیه → مسیر auto_publish واقعاً فعال شد

### فاز ۴ — پروفایل‌های چندگانه و شخصی‌سازی کامل
- [x] 4-1: چند پروفایل هم‌زمان per site — جدول `site_profile_routes` (content_type → profile_id) + مسیریابی در `PolicyEvaluator`/`AutoPublish` بر اساس ContentType (مثلاً مقالات L3 با آستانهٔ ۹۰٪ کنار متا L2 با ۸۰٪)
- [x] 4-2: کپی/قالب پروفایل — `POST /app/sites/{site}/automation/profiles/copy` + UI «کپی» روی هر پروفایل
- [x] 4-3: UI مسیریابی در داشبورد + audit + به‌روزرسانی Decision/Progress Log

### بستن شکاف‌های اجرایی (2026-08-14)
- [x] 4-4: `ProcessQueuedCommands` — حلقهٔ delay → retry (cancel دستورهای منقضی + retry دستورهای queued در پنجرهٔ مجاز) هر ۳۰ دقیقه
- [x] 4-5: کانال‌های خارجی اعلان — `AutomationAlert` با mail (toMail) + تحویل تلگرام/واتساپ از طریق webhook در `RecordMetricDropAlert` با ادغام `notification_policy` از overrides (مدل سهلایه) + UI تنظیم اعلان‌ها در داشبورد خودکارسازی
- [x] 4-6: Acceptance یکپارچه — `DAcceptanceTest` کل زنجیره در یک آزمون: AutoPublish → سقف روزانه (queued) → توقف اضطراری (cancel صف) → رفع → انتشار → rollback R3 → یادگیری
- [x] 4-7: اجرایی روی DB واقعی — `php artisan migrate --force` اعمال شد + scheduler (`schedule:work`) + queue worker (`queue:work --sleep=10`) در حال اجرا

### فاز ۵ — تولید محتوا (مقاله/محصول) با استاندارد + انتشار خودکار (2026-08-15)
- [x] 5-1: **استانداردهای محتوا** — جدول `content_standards` (نسخه‌دار: content_type × subtype × intent × version) + seed اولیهٔ «دانش روز صنعت» (مقالات ۹ زیرنوع، محصولات ۴، متا ۲، لندینگ ۱) + `site_content_standard_learnings` برای یادگیری از دادهٔ سایت + `StandardsKB` (استخراج استاندارد مؤثر — بدون اعداد هاردکد)
- [x] 5-2: **تولید پیشنویس** — `GenerateArticleDraft` با دو مسیر (AI از طریق `AiClient` و rule-based با بازنویسی کامل `RuleBasedDraft`)؛ خروجی شامل متن، `featured_image` (ابعاد + alt + توضیح) و `schema` (Schema.org Product/Article/FAQ — تعیین‌شونده سمت سرور)؛ صفحات Vue مستقل «تولید پیشنویس مقاله» و «تولید پیشنویس محصول» با انتخاب سایت/پروفایل/زیرنوع
- [x] 5-3: **فاز ۲ انتشار** — `CreateArticlePublishCommand` (ساخت idempotent کامند `publish_new_article` از پیشنویس تأییدشده، payload شامل title/content/slug/schema) + هندلر `publish_new_article` در پلاگین (wp_insert_post + متای سئو) + rollback = حذف پست
- [x] 5-4: **گیت‌های انتشار خودکار** — `auto_publish_scope` (opt-in دامنه: none/meta/article/product/all) + گرمایش (`WARMUP_REQUIRED`: meta=3، product=3، article=5 اجرای موفق انسانی از همان نوع) + گیت کیفیت محتوا (`ContentQualityGuard` با استاندارد حمل‌شدهٔ پیشنویس — نه سافت‌فلور عمومی) + آستانهٔ اطمینان؛ `DecideReviewItem` پس از تأیید انسانی، کامند را می‌سازد و از AutoPublish عبور می‌دهد
- [x] 5-5: **دادهٔ واقعی ووکامرس** — `FetchWooProductInfo` + endpoint `/product-info` در پلاگین؛ قیمت/موجودی/وضعیت واقعی در جزئیات بازبینی (قبل از انتشار با اسلاگ، بعد از آن با post_id)
- [x] 5-6: **گزارش تأثیر GSC** — `BuildPublishImpactReport` (مقایسهٔ دو پنجرهٔ قبل/بعد، دلتای جایگاه/کلیک/نمایش + وضعیت، سری روزانه برای نمودار، بدون جعل عدد → `insufficient_data` با دلیل) + `BuildContentImpactSummary` (کارت «تأثیر محتوا» در داشبورد با بهترین/ضعیف‌ترین بهبود و فهرست افت‌ها) + ویجت تأثیر در جزئیات بازبینی + `gsc:import --sync` (ایمپورت هم‌گام متریک‌های روزانه)
- [x] 5-7: **افزودن `update_content` به جریان UI** — گزینهٔ «تبدیل به تغییر محتوایی» در صفحهٔ پیشنهادها + `ConvertRecommendationToCommand`
- [x] 5-8: **تست‌ها** — ۸ تست جدید (تولید مقاله، صفحهٔ مقاله، خط لولهٔ انتشار، گاردریل‌ها، جداسازی پروفایل، replay attack، جداسازی GSC، گزارش تأثیر) + به‌روزرسانی تست‌های موجود → کل سویییت ۲۹۴ تست / ۱۵۹۵ assert سبز → pint/typecheck/lint/build سبز → E2E واقعی روی WP محلی (انتشار headset-x + rollback + replay)

### فاز ۶ — تقویم محتوایی و زمان‌بندی انتشار (2026-08-16)
- [x] 6-1: **زمان‌بندی انتشار** — ستون `commands.scheduled_for` + اکشن `SchedulePublish` (زمان‌بندی/تغییر/لغو فقط برای `publish_new_article` در حالت pending_approval/scheduled با اعتبارسنجی موعد آینده) + وضعیت جدید `scheduled`
- [x] 6-2: **آزادسازی موعدرسیده** — `ReleaseScheduledCommands` job هر دقیقه: کامندهای `scheduled` با موعد رسیده → `pending_approval` → عبور از `AutoPublish` (تصمیم در لحظهٔ موعد با Policy جاری؛ خودکار یا تأیید انسانی)
- [x] 6-3: **صفحهٔ تقویم** — `ContentCalendarController` + `ContentCalendar.vue`: شبکهٔ ماه جلالی (jalaali-js)، فیلتر سایت، لیست جزئیات با وضعیت، دیالوگ زمان‌بندی؛ روت در ناوبری «گردش‌کار»
- [x] 6-4: **اتصال به جریان بازبینی** — `DecideReviewItem` + `ReviewDecisionController` با `scheduled_for` اختیاری: تأیید پیش‌نویس همراه با موعد → کامند مستقیم زمان‌بندی می‌شود
- [x] 6-5: **تست‌ها** — ۱۰ تست (زمان‌بندی/لغو/اعتبارسنجی، آزادسازی موعدرسیده با انتشار خودکار و بدون policy، ایزوله‌سازی سازمانی کنترلر) → کل سویییت **۳۰۴ تست / ۱۶۲۸ assert سبز** → pint/typecheck/lint/build سبز
- [x] 6-6: **انتشار فوری** — `SchedulePublish::publishNow` (موعد = همین لحظه + عبور بلافاصله از AutoPublish با Policy جاری) + دکمهٔ «انتشار فوری» در لیست/مودال؛ `POST …/schedule` با `action=publish_now`
- [x] 6-7: **دید هفتگی** — toggle ماه/هفته در تقویم؛ شبکهٔ ۷ روزه (شنبه–جمعه) با پیمایش هفته
- [x] 6-8: **ساخت پیش‌نویس زمان‌بندی‌شده از تقویم** — مودال (سایت، URL، عنوان، زیرنوع، موعد) → `GenerateArticleDraft` + ستون `ai_generations.scheduled_for`؛ `DecideReviewItem` هنگام تأیید، موعد پیش‌نویس را می‌خواند و کامند را زمان‌بندی می‌کند (نه انتشار فوری)
- [x] 6-9: **پیشنهاد هوشمند روز انتشار** — `SuggestPublishSlot` (میانگین کلیک هر روز هفته از `gsc_page_metrics` اخیر → بهترین روز + ساعت ۱۰:۰۰)؛ پیش‌پر کردن خودکار فیلد موعد در مودال‌ها
- [x] 6-10: **تست‌ها** — ۶ تست جدید (publish_now ×2، ساخت پیش‌نویس ×2، پیشنهاد هوشمند ×2) → کل سویییت **۳۱۰ تست / ۱۶۴۷ assert سبز** → pint/typecheck/lint/build سبز → تأیید بصری (ماه/هفته، مودال ساخت با پیش‌پرکردن پیشنهاد از GSC واقعی)
- [x] 6-11: **قانون مستندمحوری (D-020)** — ثبت در سندهای ۰۱ و ۰۴ + پیروی کامل: هر تغییر مهم در مستندات مرتبط منعکس شد
- [x] 6-12: **پیشنهاد ساعت هوشمند** — جدول `gsc_hourly_metrics` (date×hour، سطح property) + بُعد `date,hour` در ایمپورت gsc:import (`UpsertGscMetric::hour`) + `SuggestPublishSlot` بهترین ساعت را از دادهٔ ساعتی واقعی می‌خواند (fallback ساعت ۱۰:۰۰) + کارت «✨ بهترین زمان انتشار» در داشبورد (روز + ساعت + منبع داده)
- [x] 6-13: **دراگ‌اندروپ تقویم** — جابجایی پیش‌نویس بین روزها (روز عوض می‌شود، ساعت حفظ می‌شود)؛ موعد گذشته رد می‌شود (validation)
- [x] 6-14: **یادآوری موعد انتشار** — `RemindScheduledPublishes` job (روزانه ۰۹:۰۰): کامندهای `scheduled` با موعد ≤۲۴ ساعت → اعلان database به اعضای فعال سازمان (`ScheduledPublishReminder`) + dedupe با `commands.reminder_sent_at`
- [x] 6-15: **تست‌ها** — ۵ تست جدید (ساعت هوشمند، upsert ساعتی، یادآوری ×2، کارت داشبورد) → کل سویییت **۳۱۵ تست / ۱۶۶۹ assert سبز** → pint/typecheck/lint/build سبز → تأیید بصری (کارت داشبورد + دراگ‌اندروپ واقعی با جابجایی ۱۸→۲۲ آگوست)

---

## ۱۲) موارد باز (پیوسته به O های Decision Log)
- O-004: اولویت providerهای AI (OpenAI-compatible / local / چندگانه)
- O-005: allowlist نهایی Commandهای R1/R2 تجاری با تیم محصول/حقوقی
- قواعد سیاست پیش‌فرض هر پروفایل (caps و پنجره‌ها) پیش از Phase 2 نهایی می‌شود
