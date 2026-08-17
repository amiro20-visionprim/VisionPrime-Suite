# Vision Prime SUITE — UI Foundation Specification

## Visual character
Premium، آرام، دقیق، enterprise-grade. نور و فضای سفید کنترل‌شده؛ نه dark-heavy، نه gradient-heavy، نه کارت‌های بیش‌ازحد تزئینی.

## Semantic Tokens
| Token | Value / use |
|---|---|
| brand-900 | `#163B68` — primary emphasis, logo/dark text |
| brand-700 | `#1E4E86` — primary button/link |
| brand-600 | `#2B6CB0` — interactive hover/chart |
| surface-page | `#F8FBFF` |
| surface-card | `#FFFFFF` |
| text-strong | `#23364D` |
| text-default | `#4E647F` |
| text-muted | `#6E87A6` |
| border-default | `#E6EEF8` |
| success | green semantic, accessible contrast |
| warning | amber semantic, accessible contrast |
| danger | red semantic, accessible contrast |

**قانون:** Componentها فقط semantic token مصرف می‌کنند؛ hex مستقیم در component ممنوع است.

## Layout
- Desktop app: sidebar ثابت، header سبک، content max-width منطقی.
- Client portal: navigation کم‌تراکم‌تر و content narrative-first.
- Marketing: container ثابت، section spacing کنترل‌شده؛ whitespace باید هدفمند باشد.
- Base spacing unit: 4px؛ scale پیشنهادی 4/8/12/16/20/24/32/40/48/64.
- Radius: 8 برای input/button، 12 برای card، 16 برای modal/feature panels.
- Shadow: subtle فقط برای elevation واقعی؛ border اغلب کافی است.

## Component State Matrix
| Component | الزام‌ها |
|---|---|
| Button | primary/secondary/ghost/danger، hover/focus/disabled/loading، icon RTL-aware |
| Input | label، required marker، hint، error، disabled، leading/trailing content mixed-direction |
| Select | searchable در lists بزرگ، empty/no-result/loading/error |
| Table | loading skeleton، empty state، pagination، sort/filter، mobile transformation |
| Card | title، optional action، loading skeleton، empty/error variant |
| Modal/Drawer | focus trap، close behavior، destructive confirmation، mobile safe |
| Badge | neutral/info/success/warning/danger؛ فقط color نباشد، label هم داشته باشد |
| Chart | loading/empty/no-data explanation، accessible legend، consistent semantic palette |

## Screen Patterns
### Operational list page
`Page title + context + primary CTA → filter bar → table/cards → pagination`

### Detail page
`Breadcrumb → title/status/context → summary KPI → tabs → evidence/details → activity/audit`

### Review page
`Current vs Proposed → evidence → risk/policy → decision actions → mandatory note if reject/changes requested`

### Settings page
`شرح کوتاه اثر تنظیم → form grouped by risk → save state → audit/history if sensitive`

## Empty / Loading / Error Language
- Empty باید اقدام بعدی مشخص داشته باشد: «هنوز سایتی اضافه نشده است — افزودن سایت».
- Loading باید skeleton مشابه ساختار نهایی باشد، نه spinner تنها.
- Error باید human-readable و actionable باشد؛ details فنی در expandable section برای Admin.

## Motion
- Transition کوتاه 150–220ms، فقط برای state change و hierarchy.
- no parallax / noisy animation / excessive count-up.
- respects `prefers-reduced-motion`.

## Charts
- Clicks: brand blue
- Impressions: muted blue
- CTR: teal/green semantic
- Position: violet or amber با label روشن (کاهش عدد position بهتر است)
- Never rely solely on color; legend و tooltip دارند.

## فاز A — زیرساخت طراحی (انجام شد ✅ ۲۰۲۶-۰۸-۱۶)
اجرا شده در `resources/js/shared/ui/` و `resources/js/lib/` و `resources/js/directives/`:

| کامپوننت | مسئولیت | قرارداد |
|---|---|---|
| `VIcon` | wrapper یکپارچهٔ lucide | نگاشت ثابت وضعیت‌ها در کل سوئیت (check/rotate/clock/user-check/ban/sparkles/…)؛ tone: brand/success/warning/danger/neutral/violet؛ اندازه sm–xl |
| `VStatCard` | کارت KPI | آیکون + شمارندهٔ متحرک + روند + توضیح ساده + دکمهٔ «💡» hint؛ format number/percent |
| `VGuideTip` | نکتهٔ راهنما | متن از مخزن مرکزی `lib/tips.ts`؛ در پنل مشتری برای کاربر غیرفنی الزامی |
| `VBarChart` | میله‌ای گرادیانی | مقایسه با highlight؛ tooltip تعاملی؛ انیمیشن ورود |
| `VAreaChart` | مساحت گرادیانی | روند زمانی؛ tooltip؛ انیمیشن ورود |
| `VDoughnut` | دونات | سهم منابع؛ center label/value؛ legend |
| `vStagger` | انیمیشن ورود پلکانی | بچه‌های container با تأخیر 60ms؛ احترام به prefers-reduced-motion |

**قراردادهای افزوده:**
- هر وضعیت در کل سوئیت یک آیکون ثابت دارد (مثلاً «در صف انتشار» همیشه clock است).
- در پنل مشتری، کنار هر بخش حداقل یک `VGuideTip` یا hint در `VStatCard` دیده می‌شود.
- چارت‌ها SVG سفارشی‌اند (بدون وابستگی جدید) و فقط tokenهای معنایی مصرف می‌کنند.
- transition صفحات و stagger کوتاه‌اند (≤220ms) و reduced-motion را احترام می‌گذارند.

## فاز D1 — دارک‌مود (انجام شد ✅ ۲۰۲۶-۰۸-۱۷)
- **مکانیزم:** کلاس `dark` روی `<html>` (Tailwind v4 کلاسی با `@custom-variant dark`) + اورراید متغیرهای سمنتیک در بلوک `.dark` — بدون `dark:` پراکنده. چون UI روی توکن ساخته شده، کل سوئیت (آژانس + مشتری + مارکتینگ) خودکار جابه‌جا می‌شود.
- **جفت‌های وضعیت** (`bg-*-50` + `text-*-600/700`) با هم flip می‌شوند تا چیپ‌ها در دارک خوانا بمانند.
- **کنترلر تم:** `lib/theme.ts` (light/dark/system، localStorage `suite-theme`، همگام با OS) + `initTheme()` در بوت + اسکریپت ضد-فلش در `app.blade.php`.
- **کلید جابه‌جایی:** `VThemeToggle` در هدر هر دو پنل (آژانس کنار زنگ، مشتری کنار «راهنما و پشتیبانی»).
- **قانون:** رنگ جدید فقط از توکن‌ها؛ سطح‌های روشن هاردکد ممنوع (مگر اورلی عمدی روی گرادیان).
- جزئیات کامل (توکن‌ها + قرارداد کامپوننت) در سند ۱۶.
