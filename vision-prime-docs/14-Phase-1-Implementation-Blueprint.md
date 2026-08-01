# Vision Prime — Phase 1 Implementation Blueprint

## هدف Phase 1
ساخت یک foundation اجرایی، RTL-first، قابل توسعه و secure که Marketing، Operations App و Client Portal روی یک Design System مشترک اجرا شوند؛ بدون ورود به business logic ماژول‌های SEO.

## خروجی قابل تحویل
- Laravel 12 + PHP 8.3 + PostgreSQL + Redis/Horizon آماده اجرا
- Inertia.js 2 + Vue 3 + TypeScript + Tailwind
- Auth shell و route boundary
- Organization-aware foundation
- RTL/LTR و Jalali display utility
- Design token و component primitiveهای واقعی
- Marketing, App, Client layouts و navigation
- صفحات placeholder هدفمند، responsive و testable

## معماری Modular Monolith

```text
app/
  Domains/
    Identity/
    Organization/
    Workspace/
    Audit/
    Shared/
  Http/
    Controllers/
    Middleware/
    Requests/
  Providers/
  Support/

resources/js/
  app/
    layouts/
    pages/
    components/
    composables/
    types/
    lib/
  marketing/
    pages/
    components/
  client/
    pages/
    components/
  shared/
    ui/
    layouts/
    icons/
    types/
    utils/
```

### قواعد Domain
- Controller فقط orchestration: validate → authorize → call action/service → response.
- Domain Action یک use case مشخص دارد؛ نام‌ها فعل‌محورند، مثل `CreateOrganization`, `CreateClient`.
- Queryهای پیچیده در Query class یا Repository محدود به Domain قرار می‌گیرند، نه Controller/Vue.
- Eloquent modelها داخل Domain خود قرار می‌گیرند یا با namespace واضح در `app/Models` نگه‌داری می‌شوند؛ یک convention انتخاب و ثابت می‌شود.
- هیچ Domain نباید مستقیم به internals Domain دیگر دست‌کاری کند؛ از Action/Event/contract استفاده می‌شود.
- Shared فقط برای primitiveهای واقعاً عمومی است؛ تبدیل‌شدن Shared به dumping ground ممنوع است.

## ساختار پیشنهادی هر Domain
```text
app/Domains/Workspace/
  Actions/
  Data/
  Events/
  Models/
  Policies/
  Queries/
  Services/
  database/migrations/
  routes.php              # فقط اگر route domain-local انتخاب شود
  tests/Feature/
  tests/Unit/
```

## Multi-tenancy Hybrid
### تصمیم اجرایی اولیه
- SaaS: یک PostgreSQL مرکزی و logical isolation با `organization_id`.
- Private deployment: همان codebase، database و environment مستقل برای مشتری Enterprise.
- در SaaS هیچ tenant switching database در Phase 1 انجام نمی‌شود؛ همه Queryها organization scoped هستند.

### کنترل‌ها
- `CurrentOrganization` middleware/context.
- Global scope فقط در مدل‌هایی که مطمئن و قابل تست‌اند؛ برای مدل‌های حساس علاوه بر آن explicit query scope لازم است.
- Policy هر model ownership را بررسی می‌کند.
- unique constraintها scoped هستند: مثال `(organization_id, slug)`.

## Route Boundary
```text
Public marketing: /
Auth guest: /login, /forgot-password, /reset-password
Operations app: /app/*            → auth + verified + organization context
Client portal: /client/*          → auth + client membership + client scope
Platform admin (آینده): /platform/* → super admin only
```

## Phase 1 Route Inventory
| Surface | Route | هدف |
|---|---|---|
| Marketing | `/` | Home اولیه و CTA |
| Marketing | `/product`, `/features`, `/pricing`, `/demo` | placeholderهای واقعی، نه صفحه خالی |
| Auth | `/login` | ورود |
| App | `/app/dashboard` | shell و context-aware empty dashboard |
| Client | `/client/dashboard` | shell و executive empty state |

## Frontend Architecture
### اصول
- Aliasهای TS: `@/shared`, `@/app`, `@/marketing`, `@/client`.
- Component UI primitive فقط در `resources/js/shared/ui` نگه‌داری می‌شود.
- Feature componentها نباید primitive را duplicate کنند.
- Pageها thin هستند: composition + data props؛ logic تکراری در composable.
- Server data contract با TypeScript interface typed می‌شود.
- Zod/typed validation برای formهای پیچیده در آینده؛ Laravel FormRequest منبع حقیقت validation server است.

### Layouts
```text
shared/layouts/BaseDocumentLayout.vue
marketing/layouts/MarketingLayout.vue
app/layouts/AppLayout.vue
client/layouts/ClientPortalLayout.vue
auth/layouts/AuthLayout.vue
```

## Initial UI Primitive Inventory
- `VButton`, `VInput`, `VTextarea`, `VSelect`, `VBadge`, `VCard`
- `VTabs`, `VModal`, `VDrawer`, `VAlert`, `VTooltip`
- `VSkeleton`, `VEmptyState`, `VBreadcrumb`, `VPagination`
- `VTable`, `VPageHeader`, `VStatusDot`, `VConfirmDialog`

همه باید props typed، stateهای loading/error/disabled و RTL behavior داشته باشند.

## Environment Contract اولیه
```text
APP_NAME="Vision Prime"
APP_ENV=local
APP_URL=
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
REDIS_CLIENT=
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database|redis
VISION_PRIME_DEFAULT_LOCALE=fa
VISION_PRIME_DEFAULT_TIMEZONE=Asia/Tehran
```

Secretهای GSC/AI/Connector در Phase 1 اضافه نمی‌شوند، اما `.env.example` باید جای امن و توضیح آن‌ها را برای فازهای بعدی داشته باشد.

## Definition of Done برای Phase 1
- App build و test pass می‌شود.
- Public، App و Client routeها با layout مناسب load می‌شوند.
- هیچ style خام/random خارج از tokenها وجود ندارد.
- RTL در desktop و موبایل بررسی شده است.
- Authenticated user بدون Organization به state قابل فهم هدایت می‌شود.
- TypeScript error واضح ندارد.
- lint/format conventions مستند و اجراشدنی هستند.
