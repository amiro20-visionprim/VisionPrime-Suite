# Vision Prime SUITE — Phase 1 Atomic Task Plan

> هر Task باید در یک branch/PR مستقل یا یک اجرای محدود Agent انجام شود. Agent نباید از scope همان Task عبور کند.

## P1-01 — Repository & Runtime Baseline
**وضعیت: ✅ انجام شد — 2026-07-25**

**هدف:** پروژه Laravel قابل اجرا با ابزارهای frontend.
- [x] Laravel **12.64**، PHP 8.4 (سازگار با شرط PHP 8.3+)، Inertia 2، Vue 3، TypeScript و Tailwind 4.
- [x] PostgreSQL 17 و Redis 8 برای runtime محلی/production نصب و `.env.example` با تنظیمات PostgreSQL/Redis تنظیم شد.
- [x] Laravel Horizon نصب و scaffold شد.
- [x] `.env.example` مطابق Blueprint؛ locale فارسی و timezone تهران به‌عنوان default.
- [x] route سلامت Laravel در `/up` و صفحه Inertia پایه در `/`.
- [x] Production build و تست پایه با موفقیت اجرا شد.

**یادداشت محیط:** `.env` محلی فعلاً SQLite را برای اجرای تست‌های مستقل نگه می‌دارد؛ محیط واقعی باید از `.env.example` و credentialهای PostgreSQL/Redis استفاده کند.

**قبولی:** fresh install، migrate، build و test پایه اجرا شود.

## P1-02 — Code Quality Baseline
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] Laravel Pint با preset لاراول و `declare_strict_types` فعال شد.
- [x] ESLint Flat Config برای Vue 3 + TypeScript، با zero warnings policy.
- [x] Prettier و Tailwind class ordering plugin.
- [x] TypeScript strict config، Vue type checking و alias `@/*`.
- [x] scriptهای `lint`, `lint:fix`, `format`, `format:check`, `typecheck`, `build`.
- [x] GitHub Actions quality workflow برای format/lint/typecheck/build/Pint/test.
- [x] دستورهای تیم در `docs/DEVELOPMENT.md` ثبت شدند.
- [x] verification: lint، typecheck، build، Pint test و Laravel test همگی pass.

**قبولی:** lint/format/typecheck قابل اجرا باشد.

## P1-03 — Design Tokens & Fonts
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] Vazirmatn به‌صورت local Vite bundle و font stack فارسی/لاتین اضافه شد.
- [x] semantic tokens برای brand، surface، ink، line و stateهای success/warning/danger/info.
- [x] typography، radius، shadow، motion و focus ring contract در CSS/Tailwind 4 ثبت شد.
- [x] base reset و logical direction-ready styling اعمال شد.
- [x] Token Showcase داخلی در مسیر local/testing: `/_design-system`.
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass.

**قبولی:** یک token showcase page با تمام رنگ‌ها، type و spacing ایجاد شود.

## P1-04 — Direction & Locale Foundation
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] locale پیش‌فرض فارسی و timezone تهران در app/environment contract اعمال شد.
- [x] `syncDocumentLocale` برای همگام‌سازی `lang` و `dir` در HTML/Body بر اساس Inertia props.
- [x] utilityهای typed برای direction، تاریخ جلالی، تاریخ localized، اعداد فارسی/لاتین، درصد و metric فنی.
- [x] `jalaali-js` برای تبدیل دقیق تاریخ شمسی اضافه شد.
- [x] قرارداد mixed direction: URL/query/metric با `font-latin` و `dir="ltr"`.
- [x] Showcase داخلی در `/_localization` برای RTL، تاریخ، عدد و URL طولانی.
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass.

**قبولی:** showcase RTL/LTR، تاریخ جلالی و URL طولانی را صحیح نشان دهد.

## P1-05 — Shared UI Primitives: Inputs & Actions
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] `VButton`, `VInput`, `VTextarea`, `VSelect`, `VBadge`, `VAlert` در `resources/js/shared/ui`.
- [x] props و emitهای typed؛ `v-model` برای input/textarea/select.
- [x] stateهای loading، disabled، error، required، hint و validation message.
- [x] label/input association، aria-invalid، aria-describedby، focus state و alert role.
- [x] پشتیبانی RTL و leading/trailing slot برای Input؛ mixed direction با prop `dir`.
- [x] همه componentها در `/_design-system` با فرم تعاملی، button state، badge و alert نمایش داده شدند.
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass.

**قبولی:** stories/showcase با keyboard/focus و RTL pass.

## P1-06 — Shared UI Primitives: Containers & Feedback
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] `VCard`, `VTabs`, `VModal`, `VDrawer`, `VTooltip`, `VSkeleton`, `VEmptyState`, `VConfirmDialog`.
- [x] Modal با Teleport، Escape/backdrop policy، focus اولیه و focus trap.
- [x] Drawer responsive-ready با overlay، Escape و slot footer.
- [x] Tabs با ARIA tablist/tab/tabpanel و keyboard-compatible button semantics.
- [x] Empty, loading و confirmation patterns با copy و CTA هدفمند.
- [x] تمام حالت‌ها به‌شکل تعاملی در `/_design-system` نمایش داده می‌شوند.
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass.

**قبولی:** focus/escape/modal، mobile drawer و empty/loading/error demo pass.

## P1-07 — Shared UI Primitives: Navigation & Data
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] `VBreadcrumb`, `VPagination`, `VTable`, `VPageHeader`, `VStatusDot` ساخته شدند.
- [x] جدول typed با custom cell slots، loading skeleton، empty state، technical LTR cells و mobile cards/scroll mode.
- [x] Pagination با current page binding، ellipsis و accessible page state.
- [x] PageHeader دارای breadcrumb، context status و action slot با اولویت CTA.
- [x] نمونه‌ی صفحه عملیاتی/جدول responsive در `/_design-system` اضافه شد.
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass.

**قبولی:** sortable header visual، empty table و responsive pattern pass.

## P1-08 — Public & Auth Shell
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] AuthLayout فارسی/RTL، premium و responsive؛ login، forgot-password و reset-password pageها.
- [x] ورود، خروج، session regeneration، logout invalidation و password reset flow با Laravel Password Broker.
- [x] login attempt rate limiting در Controller و Password Rule حداقل ۱۲ کاراکتر با حروف بزرگ/کوچک و عدد.
- [x] route boundary: guest فقط برای auth pages و `auth` برای `/app/dashboard`؛ redirectهای guest/user تنظیم شد.
- [x] status flash به‌شکل Inertia shared prop.
- [x] تست‌های feature برای guest redirect، login، authenticated redirect، logout و password reset link.
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass (7 tests / 16 assertions).

**قبولی:** guest redirect و authenticated redirect تست شود.

## P1-09 — Marketing Shell
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] `MarketingLayout`، Header و Footer responsive با navigation فعال و Mobile Drawer.
- [x] صفحات `/`, `/product`, `/features`, `/pricing`, `/demo` ساخته شدند؛ همچنین `/security`, `/about`, `/contact` برای navigation کامل اضافه شد.
- [x] Copy فارسی، CTAها و Product framing بر مبنای آژانس‌ها و عملیات SEO وردپرسی نوشته شد.
- [x] preview داشبورد، workflow product و ساختار pricing/feature/security به‌شکل coherent و غیر-generic ایجاد شد.
- [x] فرم Demo با validation ظاهری و state شفاف prototype؛ persistence/lead delivery در فاز Conversion متصل می‌شود.
- [x] تست route برای ۸ صفحه عمومی؛ verification: lint، typecheck، build، Pint test و Laravel test pass (15 tests / 24 assertions).

**قبولی:** navbar موبایل، footer و CTA navigation درست کار کند.

## P1-10 — Organization Foundation
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] migrations و مدل‌های `Organization`, `Membership`, `Role`, `Permission` با logical tenant boundary.
- [x] Role/Permission seeder برای نقش‌های System و Permission catalog پایه.
- [x] `CurrentOrganization` request-scoped context و `current.organization` middleware.
- [x] انتخاب Organization از session، fallback به اولین membership فعال و جلوگیری از switch به tenant غیرمجاز.
- [x] onboarding واقعی ساخت Organization و membership با نقش Agency Admin.
- [x] current organization به‌صورت Inertia shared prop به frontend منتقل می‌شود.
- [x] تست‌های feature برای redirect onboarding، ساخت organization، منع tenant switch غیرمجاز و seeder.
- [x] verification: migration fresh + seed، lint، typecheck، build، Pint test و Laravel test pass (19 tests / 33 assertions).

**قبولی:** user بدون org به onboarding؛ user عضو org به `/app/dashboard` هدایت شود.

## P1-11 — Operations App Shell
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] `AppLayout` با Sidebar دسکتاپ، Topbar، responsive Mobile Drawer و logout flow.
- [x] `AppNavigation` با گروه‌بندی IA نهایی برای workspace، intelligence، workflow و settings.
- [x] `OrganizationSwitcher` واقعی با Inertia update و context سازمان فعال.
- [x] Dashboard پایه، KPIهای honest empty-state و مسیر شروع افزودن سایت.
- [x] تمام routeهای navigation بنیادین به placeholderهای context-aware متصل شدند تا لینک شکسته وجود نداشته باشد.
- [x] shared props برای auth user، organization فعال و organizationهای مجاز.
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass (19 tests / 33 assertions).

**قبولی:** navigation routeها و responsive behavior درست باشند.

## P1-12 — Client Portal Shell
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] `ClientPortalLayout` جدا از Admin App؛ navigation ساده، responsive drawer و هیچ کنترل عملیاتی Admin در آن وجود ندارد.
- [x] `/client/dashboard` با خلاصه Executive-first، اولویت‌ها، گزارش و activity به‌صورت honest empty states.
- [x] routeهای `/client/growth`, `/client/site-health`, `/client/opportunities`, `/client/decisions`, `/client/reports`, `/client/activity` آماده و بدون link broken.
- [x] `client.portal` middleware؛ دسترسی اولیه فقط برای Client Viewer/Client Approver و Agency Admin جهت preview.
- [x] تست access برای client viewer، agency admin و منع role عملیاتی غیرمجاز.
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass (22 tests / 36 assertions).

**یادداشت:** assignment دقیق Client-to-User پس از ایجاد Domainهای Client/Project/Site در فاز بعدی به این middleware/policy افزوده می‌شود؛ تا آن زمان هیچ داده مشتری واقعی در portal نمایش داده نمی‌شود.

**قبولی:** Admin app controls در client shell نمایش داده نشوند.

## P1-13 — Audit Foundation
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] immutable `audit_logs` migration/model با actor، organization، subject، before/after، metadata، source، request ID و hashed IP.
- [x] request-scoped RequestContext و middleware `AssignRequestId` با response header `X-Request-ID`.
- [x] reusable `RecordAuditLog` action با redact recursive برای password/token/secret/authorization/api_key/cookie.
- [x] رویدادهای `organization.created`, `organization.selected`, `auth.login_succeeded`, `auth.logout` ثبت می‌شوند.
- [x] تست Audit برای organization creation/request context و redaction payload حساس.
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass (24 tests / 43 assertions).

**قبولی:** یک رویداد login یا organization created با redaction صحیح ثبت شود.

## P1-14 — QA Gate
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] `migrate:fresh --seed` با موفقیت اجرا شد؛ migration و Role/Permission seed معتبرند.
- [x] Auth boundary، Organization onboarding/context، Client Portal access و Audit scenarios با feature tests پوشش داده شدند.
- [x] Prettier check، ESLint، TypeScript، Production Vite build و Laravel Pint همگی pass شدند.
- [x] 71 route ثبت‌شده و صفحات Marketing/Operations/Client Portal بدون route break بررسی شدند.
- [x] QA checklist و intentional Phase 1 limits در `docs/PHASE-1-QA.md` ثبت شد.
- [x] `README.md` با setup و commandهای توسعه اضافه شد.
- [x] verification نهایی: 24 tests / 44 assertions.

**قبولی:** تمام Phase 1 Definition of Done در `14` پاس شود.

## ترتیب اجرای قطعی
`P1-01 → P1-02 → P1-03 → P1-04 → P1-05 → P1-06 → P1-07 → (P1-08, P1-10) → (P1-09, P1-11, P1-12) → P1-13 → P1-14`

P1-09 می‌تواند پس از P1-07 به‌صورت موازی با P1-10 اجرا شود، ولی نباید token/component جدید خارج از قرارداد بسازد.
