# Vision Prime SUITE — Atomic Delivery Plan

## روش اجرا
هر فاز فقط پس از گذر از Definition of Done بسته می‌شود. هر مورد زیر باید به Taskهای کوچک‌تر قابل انجام در یک PR/جلسه Agent شکسته شود: migration → model/policy → service/job → endpoint/controller → Vue page/component → states → tests → QA.

## Definition of Ready برای هر Task
- هدف و کاربر مشخص است
- Dependencyها مشخص‌اند
- Acceptance criteria دارد
- متن UI فارسی/انگلیسی معلوم است
- تصمیم باز ندارد؛ اگر دارد ابتدا در Decision Log بسته می‌شود

## Phase 0 — Product Operating System
- [ ] تکمیل Master Product Spec
- [ ] تعریف glossary فارسی/انگلیسی محصول
- [ ] ثبت Design tokens RTL-first
- [ ] تصمیم deployment و tenancy
- [ ] تعریف event taxonomy و audit schema
- [ ] ثبت threat model connector/command/AI

## Phase 1 — Application Foundation & Design System
- [ ] Laravel/Inertia/Vue/TS/Postgres/Redis baseline
- [ ] auth shell و route boundaries
- [ ] RTL/LTR direction provider
- [ ] Jalali display utilities
- [ ] typography: Vazirmatn + fallback strategy
- [ ] Tailwind semantic tokens
- [ ] UI primitives with loading/error/disabled states
- [ ] marketing/admin/client layouts
- [ ] responsive navigation and accessibility pass
- [ ] visual regression checklist

## Phase 2 — Identity, Tenant, RBAC, Audit
- [ ] Organization/Team schema
- [ ] Client/Project/Site schema
- [ ] role/permission seed and policies
- [ ] user assignment flows
- [ ] AuditLog immutable foundation
- [ ] admin CRUD journeys
- [ ] empty/onboarding states
- [ ] auth/RBAC feature tests

## Phase 3 — Marketing & Commercial Flows
- [ ] core marketing pages
- [ ] demo/contact forms and lead routing
- [ ] privacy/security pages
- [ ] Persian copy review
- [ ] solution landing page system
- [ ] analytics events and conversion tracking

## Phase 4 — WordPress Connector Foundation
- [ ] plugin architecture/classes
- [ ] Laravel connector schema
- [ ] pairing token lifecycle
- [ ] secret encryption/hashing policy
- [ ] HMAC signer/verifier
- [ ] timestamp/nonce/replay protection
- [ ] plugin admin connection UI
- [ ] site health endpoint and UI
- [ ] connector tests and failure states

## Phase 5 — Content Sync & URL Profiles
- [ ] safe paginated WP endpoints
- [ ] sync schema/models
- [ ] queued sync orchestration
- [ ] snapshot/hash change detection
- [ ] sync run monitoring
- [ ] URL list/detail/history UI
- [ ] operational dashboard metrics

## Phase 6 — GSC Integration
- [ ] OAuth/token encryption/refresh
- [ ] account/property selection
- [ ] import schema
- [ ] page/query import jobs
- [ ] retry/error recovery
- [ ] UrlProfile mapping + manual override
- [ ] GSC tables/charts/filters
- [ ] integration test strategy with fakes

## Phase 7 — Intelligence Engine
- [ ] Persian + English intent rules
- [ ] confidence/explanation persistence
- [ ] keyword-page mapping
- [ ] cannibalization detection
- [ ] opportunity score factor model
- [ ] score recompute jobs
- [ ] opportunity list/detail/explainability UI
- [ ] scoring service tests

## Phase 8 — Money Pages & Conversion Risk
- [ ] money page detection/manual flag
- [ ] audit rules and scoring
- [ ] conversion risk rules/severity
- [ ] recommendations model and UI
- [ ] client-safe summary projection
- [ ] explainability and override flows

## Phase 9 — AI Gateway & Review ✅ (تا ۲۰۲۶-۰۸-۱۵)
- [x] provider abstraction/settings encryption (AiClient + provider config)
- [x] prompt template versioning
- [x] usage/cost/audit logging (ai_generations.usage_json)
- [x] generation/version comparison (ai_generation_versions)
- [x] review queue/assignment/decision
- [x] AI output policy by site + **تولید مقاله/محصول با استاندارد مؤثر (`content_standards`/StandardsKB) + اسکیما + تصویر شاخص + HTML پاک‌سازی‌شده**
- [x] **بازنگری D-013/D-016:** مسیر مجاز انتشار مستقیم با اجازه‌نامه (بجای no-direct-publish مطلق) + گیت‌های scope/گرمایش/کیفیت (D-017) + تست‌های AutoPublishGuardrails

## Phase 10 — Automation Governance & Commands ✅ (تا ۲۰۲۶-۰۸-۱۵)
- [x] SiteAutomationPolicy schema/versioning (+ auto_publish_scope، active_profile_id، overrides_json)
- [x] automation level configuration UI (داشبورد 2-4 + Trust + پروفایل‌های org-scoped)
- [x] command schema and allowlist (شش نوع + publish_new_article + update_content)
- [x] policy evaluation service (PolicyEvaluator + گرمایش + کیفیت + دامنه)
- [x] approval gating (انسانی + سیستم با reviewer_type)
- [x] snapshots/rollback (endpoint /rollback پلاگین + RollbackMonitor + بازگشت بدون‌اتلاف)
- [x] command lifecycle UI (فهرست/جزئیات + وضعیت auto_publish + لینک پست)
- [x] emergency stop (EmergencyStop/ResumeAutomation + گارد DispatchCommand)
- [x] security, idempotency and rollback tests (ReplayAttackTest، DAcceptanceTest، Guardrails)

## Phase 11 — Client Portal, Reports & Impact
- [ ] client navigation and data projection policies
- [ ] executive dashboard
- [ ] decisions-needed flow
- [ ] impact timeline and before/after model
- [ ] report builder/preview/export
- [ ] report sharing/security
- [ ] client portal usability testing checklist

## Phase 12 — Production Readiness
- [ ] plugin hardening/diagnostics/log hygiene
- [ ] performance profiling/N+1/pagination/cache
- [ ] observability, Horizon and failed job operations
- [ ] backup/restore plan
- [ ] security review and penetration-test checklist
- [ ] deployment runbook/.env.example
- [ ] full RTL/mobile QA
- [ ] demo seed data
- [ ] launch checklist

## ترتیب وابستگی قطعی
Foundation → Identity → Connector/GSC → Content & Data → Intelligence → Recommendation/Review → Automation → Reporting/Impact → Hardening.

Marketing می‌تواند بعد از Foundation به‌صورت موازی اجرا شود، اما نباید Design System جداگانه بسازد.

### آپدیت #۵۰ — لوگوی اینماد در فوتر سایت (۲۰۲۶-۰۸-۱۸)

- [x] لوگوی رسمی اینماد (کد `7358907`) در فوتر سایت مارکتینگ (`MarketingFooter.vue`) قرار گرفت
- [x] هاتلینک رسمی `trustseal.enamad.ir` با `loading="lazy"` + `decoding="async"` + ابعاد ثابت (بدون افت سرعت لود و بدون layout shift)
- [x] استایل: باکس سفید گرد + سایهٔ ملایم + hover-opacity — تمیز و هماهنگ با فوتر
- [x] بیلد جدید (`app-CY5Kngc7.js`) ساخته، روی سرور استقرار و تأیید شد (HTTP 200 + رشتهٔ لینک در باندل)

### آپدیت #۵۱ — اصلاح بخش نمادها در فوتر (۲۰۲۶-۰۸-۱۸)

- [x] مشکل خالی بودن لوگوی اینماد ریشهیابی شد: هاتلینک `trustseal.enamad.ir` از مرورگر هم ۴۰۳ برمیگرداند (حفاظت ضدبات سمت اینماد)
- [x] راهحل قطعی: تصویر لوگو (کد ۷۳۵۸۹۰۷) بهصورت **محلی** در `public/images/enamad-logo.png` ذخیره شد (برشخورده با PIL: 208×120، بدون حاشیهٔ سفید)
- [x] بخش اختصاصی «نمادهای اعتماد و مجوزها» در فوتر ساخته شد — آمادهٔ افزودن نمادهای بعدی (ساماندهی و...)
- [x] لینک همچنان به صفحهٔ تأیید رسمی اینماد (`trustseal.enamad.ir/?id=7358907`) میرود
- [x] بیلد جدید (`app-zz8MSWdD.js`) استقرار یافت — تصویر HTTP 200 + type:image/png تأیید شد

### آپدیت #۵۲ — بررسی صفحهٔ ورود + مدرنسازی دکمهٔ پشتیبانی (۲۰۲۶-۰۸-۱۸)

- [x] **بررسی مشکل صفحهٔ ورود/ثبتنام:** ریشهٔ مشکل پیدا نشد در سرور (همهٔ assets 200، صفحه کامل رندر میشود) — مشکل از کش مرورگر بود؛ صفحهٔ ورود با مرورگر واقعی تأیید شد (فرم کامل + دارکمود)
- [x] **مدرنسازی دکمهٔ پشتیبانی:** آیکون 🤖 (ایموجی بات) از ویجت مشاور (FAB + هدر) حذف و با `VIcon sparkles` هماهنگ با بقیهٔ پنلها جایگزین شد
- [x] **یکپارچهسازی:** دکمهٔ پشتیبانی PlatformLayout آیکون `support` گرفت تا با AppLayout و ClientPortalLayout یکدست شود
- [x] بیلد جدید (`app-CdQhqZH6.js`) استقرار یافت + ورود سوپرادمین با curl تأیید شد (302 → /app/dashboard)
