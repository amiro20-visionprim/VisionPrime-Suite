# Vision Prime SUITE — Phase 0 Atomic Backlog

## هدف فاز صفر
حذف ابهام پیش از کدنویسی. خروجی این فاز، قرارداد محصولی و فنی است که Agent/تیم باید در هر فاز به آن رجوع کند.

## P0-01 — تثبیت مرز محصول
**خروجی:** Scope Map برای همه ماژول‌ها، Owner و dependency.
- [ ] هر ماژول به یک Product Domain متصل شود.
- [ ] ورودی/خروجی هر ماژول ثبت شود.
- [ ] موارد ممنوع (R4) در سطح محصول تأیید شوند.
- [ ] Acceptance: هیچ route یا feature بی‌مالک و بدون Domain نماند.

## P0-02 — Tenancy & Deployment Decision
**خروجی:** ADR برای Organization tenancy و مدل نصب.
- [ ] انتخاب اولیه: single database multi-tenant با `organization_id` و Policy scope، مگر خلاف آن تصویب شود.
- [ ] تعیین SaaS/private/self-hosted offering.
- [ ] تعیین encryption key و backup boundary.
- [ ] Acceptance: مدل data isolation و deployment روشن باشد.

## P0-03 — Information Architecture
**خروجی:** `05-Information-Architecture-and-Navigation.md`
- [x] Sitemap سه تجربه اصلی.
- [x] Context switcher.
- [ ] تایید labels فارسی توسط Product Owner.
- [ ] Acceptance: تمام routeهای آینده جای معلوم در navigation دارند.

## P0-04 — Roles & Access
**خروجی:** `06-RBAC-Permission-Matrix.md`
- [x] Role definitions.
- [x] Permission matrix سطح بالا.
- [ ] تبدیل matrix به permission keys دقیق.
- [ ] تعیین approval escalation policy.
- [ ] Acceptance: برای هر mutation معلوم است چه کسی مجاز است.

## P0-05 — Automation Governance
**خروجی:** `01-Automation-Governance-and-Site-Policy.md`
- [x] Levels L0-L4.
- [x] Risk tier R0-R4.
- [ ] allowlist نهایی commandها.
- [ ] تعیین default policy برای Site جدید: L1.
- [ ] تعیین approval escalation برای R3.
- [ ] Acceptance: مسیر auto/manual برای هر command قابل محاسبه است.

## P0-06 — Localization Contract
**خروجی:** `07-RTL-Localization-UX-Rules.md`
- [x] RTL/LTR, typography, Jalali rules.
- [ ] انتخاب library تاریخ جلالی سازگار Vue/TS.
- [ ] تعیین locale architecture و translation key convention.
- [ ] Acceptance: هیچ component پایه بدون RTL contract ساخته نشود.

## P0-07 — Domain Model v1
**خروجی:** `09-Domain-Model-ERD-and-Data-Dictionary.md`
- [x] هسته: Organization, User, Membership, Client, Project, Site.
- [x] Connector: SiteConnection, PairingToken, Nonce, SyncRun, ConnectorEvent.
- [x] SEO: UrlProfile, ContentSnapshot, Gsc*, KeywordInsight, Opportunity.
- [x] Workflow: Recommendation, ReviewItem, Command, RollbackSnapshot.
- [x] Governance: SiteAutomationPolicy, AuditLog.
- [ ] تعیین retention و PII/security classification نهایی برای هر entity.

## P0-08 — API & Event Contracts
**خروجی:** `11-API-Events-and-State-Contracts.md`
- [x] naming convention برای API و Inertia actions.
- [x] error response contract.
- [x] pagination/filter/sort contract.
- [x] audit event schema.
- [x] product analytics event schema.
- [ ] Acceptance: Frontend و backend در نام/statusها تناقض ندارند.

## P0-09 — Security Threat Model
**خروجی:** `12-Security-Threat-Model.md`
- [x] auth/RBAC threats.
- [x] WordPress pairing/signing/replay threats.
- [x] token/secret/encryption threats.
- [x] AI prompt/output/data leakage threats.
- [x] command/rollback and abuse threats.
- [ ] Acceptance: mitigation مالک و تست دارد.

## P0-10 — UI Foundation Specification
**خروجی:** `13-UI-Foundation-Specification.md`
- [x] semantic color tokens.
- [x] typography scale.
- [x] spacing/radius/shadow/motion scale.
- [x] state matrix برای button/input/table/modal.
- [x] dashboard/table/chart patterns.
- [ ] Acceptance: UI Phase 1 بدون طراحی مجدد و random styling قابل ساخت است.

## Gate خروج از فاز صفر
فقط زمانی وارد کدنویسی Phase 1 می‌شویم که موارد P0-02، P0-04، P0-05، P0-06 و تصمیم‌های بازِ وابسته بسته شده باشند.
