# Vision Prime SUITE — RTL, Persian Localization & UX Rules

## 1) اصل بنیادین
این محصول «ترجمه‌شده به فارسی» نیست؛ **فارسی و RTL-first** طراحی می‌شود. زبان انگلیسی یک localization دوم است.

## 2) Direction Rules
- root app بر اساس locale دارای `dir="rtl"` یا `dir="ltr"` است.
- متن فارسی، navigation، form labels و narrative RTL هستند.
- URL، slug، CSS class/code، API payload، GSC query انگلیسی و مقادیر فنی LTR هستند.
- برای data cellهای ترکیبی از `dir="auto"` فقط بعد از تست استفاده شود؛ در موارد فنی direction صریح بهتر است.
- آیکون‌های جهت‌دار (chevron, arrow, back, next) mirror می‌شوند؛ آیکون‌های مفهومی mirror نمی‌شوند.
- ترتیب visual Breadcrumb در RTL صحیح، اما semantics و keyboard traversal منطقی می‌ماند.

## 3) Typography
- UI و متن فارسی: Vazirmatn.
- اعداد/داده فنی و English UI: Inter یا Manrope.
- Heading فارسی نباید صرفاً با فونت لاتین برند ساخته شود.
- حداقل line-height متن فارسی: 1.7؛ UI dense می‌تواند کمتر، ولی خوانا باشد.
- وزن Bold بیش‌ازحد استفاده نشود؛ hierarchy با size، spacing و رنگ کنترل شود.

## 4) Dates, Numbers, Currency
- Database: UTC / ISO 8601.
- UI: Jalali default، Gregorian optional.
- date-range باید از «۷ روز گذشته»، «۳۰ روز گذشته»، «این ماه»، «ماه قبل» پشتیبانی کند.
- user preference برای digits فارسی/لاتین تعریف شود.
- URLها، position، CTR، metrics فنی و code از digits لاتین پشتیبانی قطعی دارند.
- Currency و billing برای ایران به ریال/تومان باید در product decision جداگانه نهایی شود؛ نمایش مبلغ و واحد مبهم نباشد.

## 5) Writing Rules
- لحن: حرفه‌ای، روشن، آرام، نتیجه‌محور؛ نه ادبی، نه ترجمه تحت‌اللفظی SaaS.
- از اصطلاحات یکدست استفاده شود:
  - Opportunity: «فرصت رشد»
  - Recommendation: «پیشنهاد»
  - Review: «بررسی»
  - Approval: «تأیید»
  - Command: «درخواست تغییر» در Client Portal؛ «دستور اجرایی» در App
  - Money Page: «صفحه درآمدزا»
  - Conversion Risk: «ریسک تبدیل»
  - Automation Policy: «سیاست خودکارسازی»
- Error message باید علت، اثر و اقدام بعدی را بگوید.
  - بد: «خطا رخ داد»
  - خوب: «اتصال سرچ کنسول کامل نشد. دسترسی حساب Google را بررسی کنید یا دوباره تلاش کنید.»

## 6) Charts, Tables and Forms
- Legend و tooltip نمودارها RTL و قابل‌خواندن‌اند؛ metricهای فنی direction مستقل دارند.
- جدول‌ها در موبایل به card/list responsive تبدیل می‌شوند، نه اینکه صرفاً horizontal scroll شوند.
- ستون Action در RTL در جای ثابت و قابل دسترس باشد.
- فرم‌ها label بالا، help text کوتاه، validation نزدیک field و CTA شفاف دارند.
- اعداد و درصدها در جدول align یکسان و قابل اسکن باشند.

## 7) Accessibility & QA Checklist
- Tab order در RTL بررسی شود.
- focus state واضح و با رنگ برند باشد.
- contrast text/border/button مطابق استاندارد قابل قبول باشد.
- متن‌های طولانی فارسی نباید layout را بشکنند.
- URLهای طولانی باید truncate قابل کپی داشته باشند.
- هر page در موبایل 360px، tablet و desktop بررسی بصری شود.
