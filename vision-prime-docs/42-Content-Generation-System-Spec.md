# 42 — Content Generation System: Technical Specification

**تاریخ:** ۲۰۲۶-۰۸-۲۲ (آپدیت شده)
**وضعیت:** تحویل شده (فاز ۱+۲ محتوا)
**مخاطب:** هر ایجنتی که روی تولید محتوا، استانداردهای SEO یا کیفیت محتوا کار می‌کند.

---

## ۱) نمای کلی

سیستم تولید محتوا یک pipeline کامل است که از **تشخیص نوع محتوا** تا **تولید HTML سئو شده** تا **ارزیابی کیفیت** و **تزریق لینک داخلی** و **تولید اسکیما** را پوشش می‌دهد.

```
ContentProfiler → StandardsKB → AiGateway → ArticleHtmlSanitizer → ContentQualityGuard → InternalLinkEngine → SchemaGenerator → SEOExpertAnalyzer
```

---

## ۲) Journey جدید (۴ مرحله‌ای)

### Journey فعلی (v2):

```
مرحله ۱: هویت محتوا
├── سایت (auto-fill اگه فقط یکی باشه)
├── عنوان مقاله
├── قالب پرامپت (اختیاری)
├── پرامپت اختیاری کاربر
└── تشخیص خودکار زیرنوع + لحن توسط AI
    ↓
مرحله ۲: طراحی ساختار (اختیاری)
├── Outline با H2/H3
├── قابلیت drag-and-drop
└── مقایسه با ساختار رقبا
    ↓
مرحله ۳: تولید + ویرایش
├── Side-by-side: محتوا + Sidebar امتیاز لایو
├── تب‌ها: محتوا / Meta / SEO / Schema
└── ذخیره خودکار
    ↓
مرحله ۴: تحلیل + اقدامات
├── تحلیل متخصص SEO (Expert Analysis)
├── ۳ دکمه: اعمال همه / انتخابی / فقط ذخیره
├── ذخیره Draft
├── کپی HTML / متن
└── انتشار در وردپرس (از طریق Connector)
```

### دو حالت تولید:

| حالت | توضیح | کاربر |
|------|--------|-------|
| ⚡ **تولید سریع** | مستقیم از مرحله ۱ → تولید مقاله | کاربر عادی |
| 📋 **با Outline** | مرحله ۱ → ۲ → ۳ → ۴ | کاربر حرفه‌ای |

---

## ۳) اجزای سیستم

### ۳.۱ ContentProfiler (`ContentProfiler.php`)

تشخیص **نوع محتوا** (article/product/meta/landing)، **زیرنوع** (tutorial/comparison/review/...) و **قصد** (informational/commercial/transactional/navigational).

**متد کلیدی:** `normalizeFa(string $text): string`

نرمالایز فارسی:
- نیم‌فاصله (U+200C) → فاصله
- ی/کسره (ي/ى) → ی
- ا/أ/آ → ا
- ک/ك → ک
- ۰-۹ → 0-9
- فاصله‌های اضافی → حذف
- toLowerCase

**زیرنوع‌های پشتیبانی شده:**

| نوع | زیرنوع‌ها |
|-----|----------|
| article | tutorial, how_to, comparison, review, listicle, pillar, guide, news, faq |
| product | short_desc, long_desc, comparison, technical |
| landing | sales |

---

### ۳.۲ StandardsKB (`StandardsKB.php`)

پایگاه استانداردهای پویا — مدل سهلایه:
1. **L1 Seed** — استاندارد صنعت (versioned در دیتابیس `content_standards`)
2. **L2 Learned** — یادگیری از عملکرد سایت (`site_content_standard_learnings`)
3. **L3 Manual** — تنظیم دستی (فاز آینده)

**Safety Floor** (هاردکد، غیرقابل override):

| نوع | word_min | min_headings | min_title | max_title | min_meta_desc | max_meta_desc |
|-----|----------|-------------|-----------|-----------|--------------|--------------|
| article | 300 | 2 | 30 | 60 | 120 | 160 |
| product | 80 | 1 | 30 | 60 | 120 | 160 |
| meta | 20 | 0 | 30 | 60 | 120 | 160 |
| landing | 400 | 2 | 30 | 60 | 130 | 160 |

---

### ۳.۳ Prompt Template Library (`prompt_templates`)

کتابخانه پرامپت‌های آماده برای انواع محتوا:

| ID | عنوان | نوع | لحن |
|----|-------|------|------|
| 1 | راهنمای جامع آموزشی | article/tutorial | educational |
| 2 | مقایسه تخصصی محصولات | article/comparison | analytical |
| 3 | بررسی و نقد حرفه‌ای | article/review | analytical |
| 4 | لیست پیشنهادی | article/listicle | engaging |
| 5 | مقاله خبری | article/news | journalistic |
| 6 | توضیحات محصول فروشگاهی | product/short_desc | persuasive |
| 7 | مقاله پیلار | article/pillar | authoritative |
| 8 | سوالات متداول | article/faq | informative |
| 9 | قالب دستی کاربر | custom | custom |

**ذخیره قالب کاربر:** کاربر می‌تونه پرامپت اختیاری خودش رو ذخیره کنه با لیبل "ساخته شده توسط کاربر".

---

### ۳.۴ SEO Expert Analyzer (`SEOExpertAnalyzer.php`)

تحلیل تخصصی محتوا پس از تولید:

**خروجی:**
```php
[
    'summary' => string,        // خلاصه ۴-۵ خط
    'strengths' => array,       // نقاط قوت
    'weaknesses' => array,      // نقاط ضعف
    'suggestions' => array,     // پیشنهادات بهبود
    'score' => int,             // امتیاز ۰-۱۰۰
]
```

**۳ دکمه اعمال پیشنهادات:**
1. **اعمال همه** → AI محتوا رو بازنویسی میکنه
2. **اعمال انتخابی** → کاربر تیک بزنه کدوما اعمال بشه
3. **فقط ذخیره** → تحلیل ذخیره بشه ولی محتوا تغییر نکنه

---

### ۳.۵ ContentQualityGuard (`ContentQualityGuard.php`)

ارزیابی کیفیت محتوا قبل از انتشار — مشابه RankMath/Yoast.

**گیت‌ها:**

| # | گیت | نوع | توضیح |
|---|------|------|--------|
| 1 | طول محتوا | failure | word_min ≤ length ≤ word_max |
| 2 | ساختار heading | failure | تعداد h2 ≥ min_headings |
| 3 | سلسله‌مراتب heading | failure | h1→h2→h3 بدون پرش |
| 4 | عناصر الزامی | failure | هر عنصر از required_elements |
| 5 | کلیدواژه در عنوان | failure | title_required |
| 6 | کلیدواژه در مقدمه | failure | intro_required |
| 7 | تراکم کلیدواژه (حداقل) | warning | density ≥ density_min |
| 8 | تراکم کلیدواژه (حداکثر) | failure | density ≤ density_max |
| 9 | ناخالصی | failure | placeholder ممنوع |
| 10 | عنوان خالی نباشد | failure | عنوان اجباری |
| 11 | Meta title length | failure/warning | 30-60 کاراکتر |
| 12 | Meta description length | failure/warning | 120-160 کاراکتر |
| 13 | لینک‌های داخلی | failure | min_links ≤ count |
| 14 | FAQ existence | failure | require_faq = true |
| 15 | Schema markup | warning | وجود اسکیما |

---

### ۳.۶ InternalLinkEngine (`InternalLinkEngine.php`)

لینک‌سازی داخلی هوشمند بر اساس شباهت موضوعی.

**الگوریتم:**
1. استخراج کلمات عنوان و کلیدواژه هدف
2. مقایسه با تمام URL profiles سایت
3. محاسبه امتیاز ترکیبی:
   - **Title similarity** (40%): cosine similarity بین عنوان‌ها
   - **Keyword overlap** (35%): تعداد کلمات مشترک
   - **Content type bonus** (15%): مقاله↔محصول
   - **Recency bonus** (10%): صفحات جدیدتر

---

### ۳.۷ SchemaGenerator (`SchemaGenerator.php`)

تولید اسکیمای Schema.org بر اساس نوع محتوا.

| نوع | اسکیما |
|-----|--------|
| article | Article / NewsArticle / BlogPosting |
| product | Product (با price/availability) |
| tutorial/how_to | HowTo |
| review | Review (با rating) |
| listicle | ItemList |
| همه | BreadcrumbList |
| +faq | FAQPage (استخراج خودکار) |

---

### ۳.۸ AiGateway (`AiGateway.php`)

Gateway حرفه‌ای با قابلیت‌های:

- **پشتیبانی از چندین Provider:** OpenAI, OpenRouter, Anthropic, Groq, GapGPT
- **تشخیص خودکار لیمیت:** 429/402 → سوئیچ به مدل بعدی
- **Retry حداکثر ۳ بار** → Fallback RuleBased
- **Cache وضعیت لیمیت** (60 ثانیه)
- **HTTP Proxy برای OpenRouter** (رفع مشکل SSL)

**مدل‌های موجود:**

| Provider | مدل‌ها | رایگان |
|----------|--------|--------|
| OpenRouter | qwen-30b-a3b, gemini-3.1-flash-lite-preview | ✅ |
| Groq | llama-3.3-70b-versatile, gemma2-9b-it | ✅ |
| GapGPT | gpt-4o-mini, qwen-3.6 | ❌ (پولی) |
| Anthropic | claude-sonnet-4-20250514 | ❌ (پولی) |

---

### ۳.۹ ContentDraft (`content_drafts`)

ذخیره خودکار محتوای تولید شده:

| فیلد | توضیح |
|------|--------|
| site_id | شناسه سایت |
| content_type | article/product |
| title | عنوان محتوا |
| content | HTML محتوا |
| meta_title | عنوان SEO |
| meta_description | توضیحات SEO |
| schema_json | اسکیمای Schema.org |
| quality_score | امتیاز کیفیت ۰-۱۰۰ |
| ai_model | مدل استفاده شده |
| template_id | شناسه قالب پرامپت |
| audit_log | لاگ عملیات |
| expert_analysis | تحلیل متخصص SEO |
| status | draft/review/published/archived |

---

## ۴) WordPress Integration

### ۴.۱ Vision Prime Connector (Plugin v1.2.0)

اتصال امن با وردپres از طریق پلاگین رسمی:

**ویژگی‌ها:**
- Pairing Token یکبار مصرف
- HMAC Signature برای هر درخواست
- Rollback snapshot برای بازگشت تغییرات
- پشتیبانی از RankMath و Yoast
- پشتیبانی از WooCommerce

**جریان اتصال:**
```
۱. نصب پلاگین در وردپرس
۲. وارد کردن Platform URL + Site ID
۳. دریافت Pairing Token از پلتفرم
۴. وارد کردن توکن در پلاگین
۵. اتصال برقرار ✅
```

**دستورات پشتیبانی شده:**

| دستور | توضیح |
|-------|--------|
| `publish_new_article` | انتشار مقاله جدید |
| `update_meta_title` | بروزرسانی عنوان SEO |
| `update_meta_description` | بروزرسانی توضیحات SEO |
| `update_content` | بروزرسانی محتوا |
| `update_product_title` | بروزرسانی عنوان محصول |
| `update_product_description` | بروزرسانی توضیحات محصول |

### ۴.۲ انتشار از طریق Command System

**روش صحیح انتشار:**
```
۱. ایجاد Command در جدول `commands`
۲. با type = `publish_new_article`
۳. با payload شامل: title, content, meta, schema
۴. ارسال از طریق `DispatchCommand`
۵. دریافت نتیجه از WordPress
```

**⚠️ نکته مهم:** از `WordPressPublisher.php` استفاده نکنید! این فایل از Basic Auth استفاده میکنه که امن نیست. همیشه از سیستم Command/Dispatch استفاده کنید.

---

## ۵) API Endpoints

| Endpoint | Method | توضیح |
|----------|--------|--------|
| `/api/content/outline` | POST | تولید Outline |
| `/api/content/generate` | POST | تولید محتوا + کیفیت + لینک + اسکیما |
| `/api/content/score` | POST | امتیازدهی RankMath لحظه‌ای |
| `/api/content/links` | POST | لینک‌سازی داخلی |
| `/api/content/schema` | POST | پیش‌نمایش اسکیما |
| `/api/content/providers` | GET | وضعیت مدل‌ها |
| `/api/content/test-provider` | POST | تست اتصال |
| `/api/content/gsc-context` | GET | دریافت Context GSC |
| `/api/content/apply-suggestions` | POST | اعمال پیشنهادات Expert |
| `/api/content/templates` | GET | لیست قالب‌های پرامپت |
| `/api/content/user-templates` | GET/POST | قالب‌های کاربر |
| `/api/content/drafts` | GET | لیست Draftها |
| `/api/content/drafts/{id}` | DELETE | حذف Draft |
| `/api/content/publish-wp` | POST | انتشار در وردپرس |

---

## ۶) جدول محتوایی استانداردها

| زیرنوع | word_min | word_max | min_headings | schema | tone |
|--------|----------|----------|-------------|--------|------|
| article | 600 | 3000 | 3 | Article | informative |
| tutorial | 800 | 4000 | 4 | HowTo | educational |
| how_to | 800 | 4000 | 4 | HowTo | educational |
| comparison | 800 | 3000 | 3 | Article | analytical |
| review | 1000 | 3500 | 4 | Review | analytical |
| listicle | 800 | 3000 | 3 | ItemList | engaging |
| pillar | 1500 | 6000 | 6 | Article | authoritative |
| guide | 1200 | 5000 | 5 | Article | authoritative |
| news | 300 | 1000 | 2 | NewsArticle | journalistic |
| faq | 500 | 2000 | 3 | FAQPage | informative |
| short_desc | 80 | 200 | 0 | Product | persuasive |
| long_desc | 200 | 800 | 2 | Product | persuasive |
| technical | 300 | 1200 | 3 | Product | technical |
| sales | 400 | 1500 | 3 | WebPage | persuasive |

---

## ۷) فایل‌های کلیدی

| فایل | مسیر | توضیح |
|------|------|--------|
| AiGateway.php | app/Domains/Ai/Services/ | Gateway اصلی AI |
| ContentApiController.php | app/Http/Controllers/App/ | API Endpointها |
| AiDraftController.php | app/Domains/Content/Controllers/ | مدیریت Draftها |
| ContentDraft.php | app/Domains/Content/Models/ | مدل Draft |
| PromptTemplate.php | app/Domains/Content/Models/ | مدل قالب پرامپت |
| SEOExpertAnalyzer.php | app/Domains/Content/Services/ | تحلیلگر متخصص |
| ContentQualityGuard.php | app/Domains/Content/Services/ | نگهبان کیفیت |
| InternalLinkEngine.php | app/Domains/Content/Services/ | موتور لینک‌سازی |
| SchemaGenerator.php | app/Domains/Content/Services/ | تولیدکننده اسکیما |
| Create.vue | resources/js/Pages/App/ArticleDraft/ | صفحه تولید مقاله |
| ProductDraft/Create.vue | resources/js/Pages/App/ProductDraft/ | صفحه تولید محصول |
| Index.vue | resources/js/Pages/App/ArticleDraft/ | لیست Draftها |

---

## ۸) فازهای بعدی

| فاز | توضیح | وضعیت |
|-----|--------|-------|
| فاز ۱ | Journey ۴ مرحله‌ای + Prompt Templates | ✅ تکمیل |
| فاز ۱.۱ | SEO Expert Analyzer + Audit Log | ✅ تکمیل |
| فاز ۱.۲ | Quick Generate + Publish Button | ✅ تکمیل |
| فاز ۲ | WordPress Connector Integration | 🔄 در حال انجام |
| فاز ۳ | GSC واقعی و تولید محتوا با داده لحظه‌ای | 📋 برنامه‌ریزی شده |
| فاز ۴ | Content Calendar و زمان‌بندی انتشار | 📋 برنامه‌ریزی شده |
| فاز ۵ | A/B Testing و بهینه‌سازی | 📋 برنامه‌ریزی شده |
