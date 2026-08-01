# Vision Prime — Atomic Delivery Plan

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

## Phase 9 — AI Gateway & Review
- [ ] provider abstraction/settings encryption
- [ ] prompt template versioning
- [ ] usage/cost/audit logging
- [ ] generation/version comparison
- [ ] review queue/assignment/decision
- [ ] AI output policy by site
- [ ] no-direct-publish enforcement tests

## Phase 10 — Automation Governance & Commands
- [ ] SiteAutomationPolicy schema/versioning
- [ ] automation level configuration UI
- [ ] command schema and allowlist
- [ ] policy evaluation service
- [ ] approval gating
- [ ] snapshots/rollback
- [ ] command lifecycle UI
- [ ] emergency stop
- [ ] security, idempotency and rollback tests

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
