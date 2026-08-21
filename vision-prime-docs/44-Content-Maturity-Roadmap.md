# 44 — Content Generation Maturity Roadmap: صفحه تولید مقاله از نوزاد تا بلوغ

**تاریخ:** ۲۰۲۶-۰۸-۲۱  
**وضعیت:** مصوب — آماده اجرا  
**مخاطب:** هر ایجنتی که روی تولید محتوا کار می‌کند.

---

## ۱) نمای کلی وضعیت فعلی

### ۱.۱ چیزایی که داریم (موجود)
- ✅ فرم عنوان → سایت → زیرنوع → دکمه تولید
- ✅ AiGateway با failover chain (GapGPT → OpenRouter → RuleBased)
- ✅ ContentGuardrails (حداکثر/حداقل کلمه، لحن، تگ‌ها، CTA/FAQ)
- ✅ امتیاز SEO بعد از تولید (6 check)
- ✅ Schema.org generation
- ✅ Meta Title/Description با length indicator
- ✅ نمایش محتوای تولید شده (HTML)

### ۱.۲ چیزایی که نداریم (شکاف)
- ❌ Outline preview قبل تولید
- ❌ SERP competitor analysis
- ❌ GSC واقعی در پرامپت ( عددای صفر!)
- ❌ Keyword density لحظه‌ای حین ویرایش
- ❌ Readability score فارسی
- ❌ لینک داخلی واقعی در محتوا (فقط لیست)
- ❌ ویرایش section-by-section
- ❌ ذخیره draft به دیتابیس
- ❌ چک محتوای تکراری
- ❌ تصویر + alt text suggestion

---

## ۲) نقشه راه سه فازی

### فاز ۱: بلوغ پایه (MVP+) — حیاتی
> هدف: صفحه از "نوزاد" تبدیل بشه به "بچه ۵ ساله" که راه میره

| # | ماژول | ارزش |
|---|-------|------|
| ۱.۱ | Outline Preview | کنترل کاربر قبل تولید |
| ۱.۲ | GSC واقعی در پرامپت | محتوای data-driven |
| ۱.۳ | ذخیره Draft + لیست مقالات | قابلیت بازیابی |

### فاز ۲: بلوغ میانی — خیلی مهم
> هدف: رقابت با ابزارهای حرفه‌ای

| # | ماژول | ارزش |
|---|-------|------|
| ۲.۱ | SERP Intelligence | شناخت رقبا |
| ۲.۲ | لینک داخلی واقعی در محتوا | On-page SEO |
| ۲.۳ | Readability Score فارسی | کیفیت خوانش |

### فاز ۳: بلوغ نهایی — تکمیلی
> هدف: ابزار نهایی و کامل

| # | ماژول | ارزش |
|---|-------|------|
| ۳.۱ | Section-by-section edit | ویرایش دقیق |
| ۳.۲ | Keyword Density لحظه‌ای | بهینه‌سازی |
| ۳.۳ | چک محتوای تکراری | جلوگیری از duplicate |

---

## ۳) جزئیات هر ماژول

### ۳.۱ — ماژول ۱.۱: Outline Preview

**درد:** کاربر عنوان میده و بدون اینکه بفهمه چی قراره تولید بشه، کلیک می‌کنه. خروجی ممکنه ساختار نامناسبی داشته باشه.

**پادزهر:** قبل تولید، AI یک outline (ساختار H2/H3) پیشنهاد بده. کاربر تایید یا ویرایش کنه. بعد تولید شروع بشه.

**جریان کاربر:**
```
[عنوان + سایت + زیرنوع] 
    → کلیک "تولید outline"
    → AI outline پیشنهادی: 
        H2: مقدمه
        H2: ویژگی‌های اصلی
            H3: سرعت
            H3: کیفیت
        H2: مقایسه با رقبا
        H2: معایب
        H2: نتیجه‌گیری
    → کاربر: drag-and-drop reorder / حذف / افزودن
    → کلیک "تولید مقاله"
    → AI مقاله رو دقیقاً بر اساس outline تولید می‌کنه
```

**تسک‌های اتمیک:**

| # | تسک | توضیح | خروجی |
|---|------|-------|-------|
| ۱.۱.۱ | اضافه کردن API `/api/content/outline` | دریافت عنوان+زیرنوع → خروجی JSON array از H2/H3 items | ContentApiController.php |
| ۱.۱.۲ | پرامپت outline در AiGateway | متد `generateOutline()` با system prompt مخصوص outline | AiGateway.php |
| ۱.۱.۳ | اضافه کردن استیت `outline` به Vue | ref برای ذخیره outline + state 'outline' بین input و generating | ArticleDraft/Create.vue |
| ۱.۱.۴ | کامپوننت OutlineEditor | Drag-and-drop list از H2/H3 items + add/remove/reorder | OutlineEditor.vue |
| ۱.۱.۵ | ارسال outline به API generate | backend مقاله رو بر اساس outline تولید کنه (نه فقط عنوان) | ContentApiController + AiGateway |
| ۱.۱.۶ | تست flow کامل | انتخاب سایت → عنوان → outline → edit → generate → نتیجه | Manual test |

---

### ۳.۲ — ماژول ۱.۲: GSC واقعی در پرامپت

**درد:** الان وقتی به AI میگیم "داده GSC: کلیک: 0، نمایش: 0" — AI فکر میکنه صفحه جدیده و محتوای عمومی تولید می‌کنه.

**پادahrenheit:** قبل تولید، query واقعی مرتبط با عنوان رو از GSC بگیر و CTR/Position/Impressions رو به AI بده تا محتوای هوشمندتر تولید کنه.

**جریان:**
```
[عنوان: "بهترین هدفون بی‌سیم ۲۰۲۶"]
    → سیستم خودکار query واقعی پیدا کنه:
        "هدفون بی‌سیم" → 15,000 impressions, position 8.2
        "بهترین هدفون" → 8,500 impressions, position 12.1
        "هدفون سونی" → 3,200 impressions, position 5.6
    → این داده‌ها رو به prompt AI اضافه کنه
    → AI محتوایی تولید کنه که:
        - CTR gap رو پوشش بده
        - کوئری‌های با position بالا رو هدف بگیره
        - از کوئری‌های با impressions بالا ولی CTR پایین استفاده کنه
```

**تسک‌های اتمیک:**

| # | تسک | توضیح | خروجی |
|---|------|-------|-------|
| ۱.۲.۱ | متد `gscContextForTitle()` | جستجو در keyword_insights بر اساس عنوان → پیدا کردن queryهای مرتبط | ContentApiController.php |
| ۱.۲.۲ | محاسبه CTR Gap | (impressions * position_factor) - clicks = فرصت | Helper method |
| ۱.۲.۳ | غنی‌سازی prompt با GSC data | اضافه کردن top queries + CTR gaps + position data به user prompt | AiGateway::articlePrompts() |
| ۱.۲.۴ | نمایش GSC data در UI | قبل تولید، نشون بده "X کوئری مرتبط پیدا شد" با جزئیات | ArticleDraft/Create.vue |
| ۱.۲.۵ | تست با سایت واقعی | اگه GSC متصل باشه، ببین آیا واقعاً queryها پیدا میشن | Manual test |

---

### ۳.۳ — ماژول ۱.۳: ذخیره Draft + لیست مقالات

**درد:** مقاله تولید میشه ولی هیچی ذخیره نمیشه! صفحه رو ببندی همه چیز از بین میره.

**پادزهر:** بعد تولید، مقاله خودکار ذخیره بشه + صفحه لیست مقالات + قابلیت ویرایش مجدد.

**جریان:**
```
[تولید مقاله موفق]
    → ذخیره خودکار در content_drafts:
        site_id, title, content, meta_title, meta_description,
        schemas, quality_score, subtype, model_used, status='draft'
    → کاربر به صفحه "پیش‌نویس‌ها" ریدایرکت بشه
    → لیست مقالات با فیلتر وضعیت (draft/review/published)
    → کلیک هر مقاله → ویرایش + بازتولید
```

**تسک‌های اتمیک:**

| # | تسک | توضیح | خروجی |
|---|------|-------|-------|
| ۱.۳.۱ | Migration جدول `content_drafts` | site_id, title, slug, content(longText), meta_title, meta_description, schemas(json), quality_score, subtype, model_used, status, created_at, updated_at | Migration |
| ۱.۳.۲ | مدل `ContentDraft` | Eloquent model با relationship به Site | Model |
| ۱.۳.۳ | ذخیره خودکار بعد تولید | در API generate، بعد موفقیت → save draft | ContentApiController |
| ۱.۳.۴ | صفحه لیست مقالات | فیلتر وضعیت + جستجو + مرتب‌سازی | ArticleDraft/Index.vue |
| ۱.۳.۵ | صفحه ویرایش مقاله | بارگذاری draft → ویرایش → save | ArticleDraft/Edit.vue |
| ۱.۳.۶ | Route + Controller | web.php routes + controller methods | Routes + Controller |
| ۱.۳.۷ | تست ذخیره + بازیابی | تولید → ذخیره → لیست → ویرایش | Manual test |

---

### ۳.۴ — ماژول ۲.۱: SERP Intelligence

**درد:** بدون شناخت رقبا، محتوا تولید کردن مثل تیر زدن در تاریکیه.

**پادزهر:** تحلیل ساختار محتوای صفحات رتبه اول Google برای کلیدواژه هدف.

**تسک‌های اتمیک:**

| # | تسک | توضیح |
|---|------|-------|
| ۲.۱.۱ | API `/api/content/serp-analysis` | جستجوی کلیدواژه → لیست ۱۰ نتیجه اول (از OpenRouter یا scraping) |
| ۲.۱.۲ | تحلیل ساختار رقبا | تعداد H2، طول محتوا، نوع محتوا، وجود FAQ/جدول/لیست |
| ۲.۱.۳ | پیشنهاد تفاوت | "رقبا FAQ ندارن → شما FAQ اضافه کنید" |
| ۲.۱.۴ | UI SERP Analysis card | نمایش نتایج رقبا + پیشنهادات قبل تولید |

---

### ۳.۵ — ماژول ۲.۲: لینک داخلی واقعی در محتوا

**درد:** الان لینک‌ها فقط در sidebar نمایش داده میشه. AI اونها رو در HTML قرار نمیده.

**پادزهر:** AI خودش anchor text مناسب در محتوا قرار بده + بعد تولید، لینک‌ها رو validate کنه.

**تسک‌های اتمیک:**

| # | تسک | توضیح |
|---|------|-------|
| ۲.۲.۱ | غنی‌سازی prompt با لینک‌ها | `linksText` در articlePrompts → AI anchor text مناسب قرار بده |
| ۲.۲.۲ | پست‌پروسس لینک‌ها | بعد تولید، چک کن لینک‌های internal واقعاً وجود دارن |
| ۲.۲.۳ | UI لینک‌ها | نمایش لینک‌های یافته شده در محتوا با highlight |

---

### ۳.۶ — ماژول ۲.۳: Readability Score فارسی

**درد:** فقط word count و SEO score داریم. نمیدونیم محتوا خوانا هست یا نه.

**پادزهر:** محاسبه readability فارسی (Flesch-Kincaid ساده) + نمایش لحظه‌ای.

**تسک‌های اتمیک:**

| # | تسک | توضیح |
|---|------|-------|
| ۲.۶.۱ | ReadabilityService.php | محاسبه avg sentence length + avg word length فارسی |
| ۲.۶.۲ | اضافه کردن به quality check | readability score در evaluate() |
| ۲.۶.۳ | UI readability indicator | نمایش "خوانایی: عالی/متوسط/ضعیف" در کنار امتیاز |

---

### ۳.۷ — ماژول ۳.۱: Section-by-Section Edit

**درد:** regenerate = همه چیز از اول. اگه فقط یه پاراگراف بده باشه، باید کل مقاله رو از نو بسازی.

**پادزهر:** هر H2 رو جداگانه regenerate + inline edit.

**تسک‌های اتمیک:**

| # | تسک | توضیح |
|---|------|-------|
| ۳.۱.۱ | تقطیع محتوا به sections | Parse HTML → array of {heading, content} |
| ۳.۱.۲ | API `
`/api/content/regenerate-section` | دریافت section + context → تولید مجدد فقط اون section |
| ۳.۱.۳ | UI section edit | دکمه "تولید مجدد" روی هر section + inline textarea |

---

### ۳.۸ — ماژول ۳.۲: Keyword Density لحظه‌ای

**تسک‌های اتمیک:**

| # | تسک | توضیح |
|---|------|-------|
| ۳.۲.۱ | computed keywordDensity | محاسبه لحظه‌ای density حین تایپ/ویرایش |
| ۳.۲.۲ | UI density bar | نمایش "تراکم: 2.3% (بهترین: 1-3%)" |

---

### ۳.۹ — ماژول ۳.۳: چک محتوای تکراری

**تسک‌های اتمیک:**

| # | تسک | توضیح |
|---|------|-------|
| ۳.۳.۱ | جستجوی عنوان مشابه | قبل تولید، چک کن عنوان مشابه در content_drafts وجود داره |
| ۳.۳.۲ | هشدار کاربر | "مقاله مشابه با عنوان X قبلاً تولید شده. ادامه بدید؟" |

---

## ۴) ترتیب اجرای پیشنهادی

```
فاز ۱ (هفته ۱):
  روز ۱-۲: ماژول ۱.۳ (ذخیره Draft) — چون بنیادی‌ترینه
  روز ۳-۴: ماژول ۱.۲ (GSC واقعی)
  روز ۵-۷: ماژول ۱.۱ (Outline Preview) — پیچیده‌ترین

فاز ۲ (هفته ۲):
  روز ۱-۲: ماژول ۲.۲ (لینک داخلی)
  روز ۳-۴: ماژول ۲.۳ (Readability)
  روز ۵-۷: ماژول ۲.۱ (SERP Intel)

فاز ۳ (هفته ۳):
  روز ۱-۳: ماژول ۳.۱ (Section edit)
  روز ۴: ماژول ۳.۲ (Keyword density)
  روز ۵: ماژول ۳.۳ (چک تکراری)
```

---

## ۵) معیارهای موفقیت

| معیار | الان | هدف فاز ۱ | هدف نهایی |
|-------|------|-----------|-----------|
| کنترل کاربر قبل تولید | ۰٪ | ۶۰٪ (outline) | ۹۰٪ |
| GSC data در prompt | ۰٪ (صفر) | ۸۰٪ | ۹۵٪ |
| ذخیره محتوا | ۰٪ | ۱۰۰٪ | ۱۰۰٪ |
| لینک داخلی در محتوا | ۰٪ | ۰٪ | ۸۰٪ |
| Readability score | نداریم | نداریم | ✅ |
| SERP analysis | نداریم | نداریم | ✅ |
