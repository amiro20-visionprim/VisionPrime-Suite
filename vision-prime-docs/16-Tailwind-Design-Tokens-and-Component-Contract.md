# Vision Prime SUITE — Tailwind Tokens & Component Contract

## هدف
تبدیل Design Direction به قرارداد اجرایی Tailwind. تمام UI باید از tokenهای معنایی استفاده کند، نه رنگ و فاصله تصادفی.

## Tailwind theme extension پیشنهادی
```ts
colors: {
  brand: { 50:'#F1F6FC', 100:'#E3EEF9', 200:'#C7DDF2', 500:'#2B6CB0', 600:'#245C9B', 700:'#1E4E86', 900:'#163B68' },
  canvas: '#F8FBFF',
  surface: '#FFFFFF',
  ink: { strong:'#23364D', DEFAULT:'#4E647F', muted:'#6E87A6' },
  line: { DEFAULT:'#E6EEF8', strong:'#D4E2F1' },
  success: { 50:'#EFFAF4', 600:'#168657', 700:'#116A45' },
  warning: { 50:'#FFF8E8', 600:'#B7791F', 700:'#8A5915' },
  danger: { 50:'#FFF2F2', 600:'#C53030', 700:'#9B2C2C' }
},
fontFamily: {
  sans: ['Vazirmatn', 'Inter', 'Manrope', 'system-ui', 'sans-serif'],
  display: ['Vazirmatn', 'Sora', 'Inter', 'sans-serif']
},
borderRadius: { ui:'0.5rem', card:'0.75rem', panel:'1rem' },
boxShadow: { card:'0 4px 16px rgba(22, 59, 104, .06)', panel:'0 12px 32px rgba(22, 59, 104, .08)' },
transitionDuration: { ui:'180ms' }
```

## CSS Base Contract
- CSS variables برای semantic tokenها در `:root` تعریف می‌شوند تا theme extension و componentها قابل نگه‌داری بمانند.
- `[dir='rtl']` و `[dir='ltr']` فقط برای موارد جهت‌دار استفاده می‌شوند؛ layout اصلی تا حد ممکن با logical propertyهای CSS (`ms`, `me`, `ps`, `pe`) نوشته می‌شود.
- `html { font-family: Vazirmatn, ... }` و font fallback برای Latin در سطح component/data cell پشتیبانی می‌شود.

## Component API نمونه
### VButton
```ts
interface ButtonProps {
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  disabled?: boolean
  type?: 'button' | 'submit' | 'reset'
}
```
- هنگام loading، عرض دکمه نمی‌پرد.
- disabled به‌تنهایی جای validation/error explanation را نمی‌گیرد.
- icon-only button باید accessible label داشته باشد.

### VPageHeader
```ts
interface PageHeaderProps {
  title: string
  description?: string
  breadcrumbs?: BreadcrumbItem[]
  status?: StatusDescriptor
}
```
- Context (Client/Project/Site) در header یا زیر عنوان مشخص است.
- Actionها slot هستند ولی بیش از یک primary action به‌طور پیش‌فرض نمایش داده نمی‌شود.

### VEmptyState
```ts
interface EmptyStateProps {
  title: string
  description: string
  action?: { label: string; href?: string; onClick?: () => void }
  tone?: 'neutral' | 'info'
}
```

## فاز A — کامپوننت‌های جدید (انجام شد ✅ ۲۰۲۶-۰۸-۱۶)

### VIcon
```ts
type IconName = 'activity' | 'arrow-down' | 'arrow-up' | 'ban' | 'bell' | 'building' | 'calendar' | 'calendar-clock' | 'chart-bar' | 'chart-doughnut' | 'chart-line' | 'check' | 'clock' | 'eye' | 'file' | 'gauge' | 'graduation' | 'lightbulb' | 'list' | 'megaphone' | 'news' | 'plane' | 'rotate' | 'search' | 'settings' | 'shield' | 'shopping-bag' | 'sparkles' | 'stethoscope' | 'support' | 'timer' | 'trend-down' | 'trend-up' | 'user-check' | 'users' | 'x' | 'zap'
type IconTone = 'brand' | 'success' | 'warning' | 'danger' | 'neutral' | 'violet'
interface VIconProps { name: IconName; tone?: IconTone; size?: 'sm' | 'md' | 'lg' | 'xl' }
```
- **نگاشت وضعیت‌ها (قرارداد سراسری):** اجراشده=check، برگشت=rotate، در صف=clock، منتظر تأیید=user-check، خطر=ban، خبر خوب=sparkles، پشتیبانی=support، تقویم=calendar، روند مثبت=trend-up، روند منفی=trend-down.
- **منبع آیکون:** `@lucide/vue` (نسخهٔ 1.26.0 — تایپ کامل دارد). شیم محیطی قدیمی (`resources/js/types/lucide-vue.d.ts`) **حذف شد** چون تایپ‌های پکیج را می‌پوشاند و فقط زیرمجموعه‌ای از آیکون‌ها را export می‌کرد.

### VStatCard
```ts
interface VStatCardProps {
  label: string
  value: string | number
  icon?: IconName
  tone?: IconTone
  hint?: string              // نکتهٔ «💡» — برای کاربر غیرفنی الزامی در پنل مشتری
  trend?: { direction: 'up' | 'down' | 'flat'; label: string; positive?: boolean }
  format?: 'number' | 'percent'
  prefix?: string
}
```
- **شمارندهٔ متحرک** (کوتاه، احترام به prefers-reduced-motion) روی value عددی.
- hint با دکمهٔ 💡 و tooltip نشان داده می‌شود؛ هر وضعیت در کل سوئیت یک آیکون ثابت دارد.

### VGuideTip
```ts
interface VGuideTipProps { text: string; tone?: 'neutral' | 'info' | 'success' }
```
- کامپوننت «💡 نکته» کنار بخش‌ها؛ متن از **مخزن مرکزی** (`resources/js/lib/tips.ts`) می‌آید تا یک‌جا ویرایش شود.
- در پنل مشتری برای کاربر غیرفنی الزامی است.

### چارت‌ها (SVG سفارشی — بدون وابستگی جدید)
```ts
interface VBarChartProps { labels: string[]; values: number[]; title?: string; highlightIndex?: number }
interface VAreaChartProps { labels: string[]; values: number[]; title?: string }
interface VDoughnutProps { segments: { label: string; value: number; color?: string }[]; centerLabel?: string; centerValue?: string }
```
- **گرادیان** (brand → transparent در area؛ brand با highlight در bar)، **tooltip تعاملی**، **انیمیشن ورود** کوتاه.
- رنگ‌های معنایی: کلیک=brand، نمایش=muted blue، CTR=success، position=violet/amber. هرگز فقط به رنگ تکیه نشود (legend + tooltip).

### انیمیشن‌ها
- **`vStagger` directive** (`resources/js/directives/stagger.ts`): بچه‌های یک container را پلکانی با تأخیر 60ms نشان می‌دهد.
- **transition صفحات** در `app.ts` (fade/slide کوتاه 180ms).
- همهٔ انیمیشن‌ها `prefers-reduced-motion` را احترام می‌گذارند.

### نکتهٔ فنی (تایپ‌های lucide)
- پکیج `@lucide/vue@1.26.0` تایپ کامل (3501 icon) در `dist/lucide-vue.d.ts` دارد؛ `skipLibCheck: true` در tsconfig فعال است.
- اگر تایپ آیکونی خطا داد، اول بررسی شود که شیم قدیمی در `resources/js/types/` وجود نداشته باشد.

## Responsive breakpoints
- mobile first.
- تست پایه: 360px، 768px، 1024px، 1440px.
- Sidebar در mobile به Drawer تبدیل می‌شود.
- tableهای عملیاتی در mobile به list card یا scoped horizontal scroll با ستون pinned تبدیل می‌شوند؛ تصمیم در سطح component گرفته می‌شود.

## Prohibited UI patterns
- gradient بزرگ و بی‌هدف.
- بیش از سه سطح card تو در تو.
- shadow سنگین برای تمام عناصر.
- رنگ متفاوت برای هر feature.
- tooltip به‌جای label/متن ضروری.
- icon بدون متن برای Actionهای پرریسک.
