# 42 — Content Generation System: Technical Specification

**تاریخ:** ۲۰۲۶-۰۸-۱۹
**وضعیت:** تحویل شده (فاز ۱ محتوا)
**مخاطب:** هر ایجنتی که روی تولید محتوا، استانداردهای SEO یا کیفیت محتوا کار می‌کند.

---

## ۱) نمای کلی

سیستم تولید محتوا یک pipeline کامل است که از **تشخیص نوع محتوا** تا **تولید HTML سئو شده** تا **ارزیابی کیفیت** و **تزریق لینک داخلی** و **تولید اسکیما** را پوشش می‌دهد.

```
ContentProfiler → StandardsKB → AiClient → ArticleHtmlSanitizer → ContentQualityGuard → InternalLinkEngine → SchemaGenerator
```

---

## ۲) اجزای سیستم

### ۲.۱ ContentProfiler (`ContentProfiler.php`)

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

### ۲.۲ StandardsKB (`StandardsKB.php`)

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

**خروجی `standardFor()`:**
```php
[
    'word_min' => int,
    'word_max' => int|null,
    'min_headings' => int,
    'required_elements' => array,
    'tone' => string,
    'keyword_guidance' => [
        'title_required' => bool,
        'intro_required' => bool,
        'density_min' => float,
        'density_max' => float,
    ],
    'schema_type' => string,
    'internal_link_rules' => [
        'min_links' => int,
        'max_links' => int,
        'anchor_relevant' => bool,
    ],
    'meta_title' => ['min_length' => int, 'max_length' => int],
    'meta_description' => ['min_length' => int, 'max_length' => int],
    'standard_key' => string,
]
```

---

### ۲.۳ ContentQualityGuard (`ContentQualityGuard.php`)

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

**خروجی:**
```php
[
    'passed' => bool,
    'score' => int,        // 0-100
    'failures' => array,   // مشکلات جدی
    'warnings' => array,   // نکات بهبود
    'rankmath_score' => int,
    'standard' => array,
]
```

---

### ۲.۴ InternalLinkEngine (`InternalLinkEngine.php`)

لینک‌سازی داخلی هوشمند بر اساس شباهت موضوعی.

**الگوریتم:**
1. استخراج کلمات عنوان و کلیدواژه هدف
2. مقایسه با تمام URL profiles سایت
3. محاسبه امتیاز ترکیبی:
   - **Title similarity** (40%): cosine similarity بین عنوان‌ها
   - **Keyword overlap** (35%): تعداد کلمات مشترک
   - **Content type bonus** (15%): مقاله↔محصول
   - **Recency bonus** (10%): صفحات جدیدتر

**خروجی `suggest()`:**
```php
[
    [
        'url' => string,
        'title' => string,
        'anchor' => string,
        'relevance_score' => float,
    ],
    // ... حداکثر 8 پیشنهاد
]
```

**متد `injectLinks()`:** تزریق لینک‌ها در HTML محتوا.

---

### ۲.۵ SchemaGenerator (`SchemaGenerator.php`)

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

### ۲.۶ AiClient (`AiClient.php`)

کلاینت تولید محتوا با AI — OpenAI, OpenRouter, Anthropic + fallback آفلاین.

**پرامپت مقاله شامل:**
- عنوان، کلیدواژه، نام برند، داده GSC
- الزامات فنی (طول، زیرعنوان، ساختار h1>h2>h3)
- الزامات محتوایی (عناصر از StandardsKB)
- لینک‌های داخلی پیشنهادی

---

### ۲.۷ GenerateContentBatch (`GenerateContentBatch.php`)

دستور Artisan برای تولید دسته‌ای محتوا.

```bash
php artisan content:generate-batch --site=1 --type=article --limit=5
php artisan content:generate-batch --site=1 --type=product --limit=3
php artisan content:generate-batch --site=1 --type=all --limit=10 --dry-run
```

**مراحل اجرا:**
1. دریافت URL profiles سایت
2. برای هر صفحه:
   a. دریافت keyword insight از GSC
   b. Profile کردن نوع/زیرنوع/قصد
   c. دریافت استاندارد مؤثر
   d. پیشنهاد لینک داخلی
   e. تولید محتوا با AI
   f. تزریق لینک داخلی
   g. تولید meta title/description
   h. تولید اسکیما
   i. ارزیابی کیفیت
   j. ذخیره در ai_generations + url_profiles

---

## ۳) جدول محتوایی استانداردها

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

## ۴) ملاحظات فنی

- `StandardsKB::SUBTYPE_DEFAULTS` — اگر زیرنوع جدیدی اضافه شود، هم در SUBTYPES و هم در SUBTYPE_DEFAULTS باید تعریف شود
- `InternalLinkEngine` — برای سایت‌های با کمتر از ۵ صفحه ممکن است نتایج ضعیفی برگرداند
- `SchemaGenerator::faqSchema` — فقط الگوی `<strong>پرسش:</strong>` را تشخیص می‌دهد
- `ContentQualityGuard` — خروجی `warnings` جدید است؛ مصرف‌کنندگان قبلی باید آن را نادیده بگیرند
- `AiClient` — timeout به 120s افزایش یافت (مقالات طولانی)
- `RuleBasedDraft` — social_proof و internal_links اضافه شد

---

## ۵) فازهای بعدی

| فاز | توضیح |
|-----|--------|
| فاز ۲ | اتصال وردپرس و تست واقعی |
| فاز ۳ | GSC integration و تولید محتوا با داده واقعی |
| فاز ۴ | Content Calendar و زمان‌بندی انتشار |
| فاز ۵ | Performance optimization و caching |
