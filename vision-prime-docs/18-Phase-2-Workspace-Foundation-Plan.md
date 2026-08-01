# Vision Prime — Phase 2: Workspace Foundation Plan

## هدف Phase 2
تبدیل Foundation چندسازمانی به فضای کاری واقعی برای مدیریت مشتری، پروژه و سایت؛ با Scope صحیح داده، CRUD کامل، Audit و آماده‌سازی Client Portal برای داده واقعی.

## User Journey هدف

```text
Agency Admin
→ Create Client
→ Assign Client Viewer / Client Approver
→ Create Project
→ Add Site
→ Set business importance and locale/timezone
→ See dashboard counts
→ Invite client to portal with only assigned client scope
```

## Atomic Tasks

### P2-01 — Workspace Schema & Domain Models
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] Clients, Projects, Sites, ClientUserAssignment migrations
- [x] Organization-scoped public IDs, soft delete and tenant indexes
- [x] Eloquent relations and domain model contract
- [x] Data-model integration tests
- [x] `migrate:fresh --seed`, Pint و full test suite pass (25 tests / 50 assertions)

### P2-02 — Authorization & Scope Policies
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] Client/Project/Site policies using Organization + assignment scope
- [x] Client Portal scope upgraded from role-only to assigned-client access
- [x] CurrentClient context, client selector route and scoped shared props
- [x] Permission checks and unauthorized-access tests
- [x] Audit RequestContext binding hardening verified
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass (26 tests / 57 assertions)

### P2-03 — Client CRUD
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] List/detail/create/edit/archive client
- [x] Client assignments and Client Portal member management
- [x] Audit create/update/archive/assignment
- [x] Empty/loading/error states
- [x] RTL responsive UI, tenant isolation and CRUD feature tests
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass (29 tests / 73 assertions)

### P2-04 — Project CRUD
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] Client-scoped project list/detail/create/edit/archive
- [x] Objective field and Client ownership validation
- [x] Audit create/update/archive and tenant scope tests
- [x] RTL responsive UI and honest Site onboarding empty state
- [x] verification: lint، typecheck، build، Pint test و Laravel test pass (30 tests / 80 assertions)

### P2-05 — Site CRUD & First-Site Onboarding
- Project-scoped Site create/edit/archive
- URL canonical validation, locale/timezone/business importance
- Create-first-site journey
- Audit and UI state tests

### P2-06 — Real Dashboard Counts & Context UX
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] Counts واقعی Client / Project / Site در Current Organization
- [x] Next-step CTA براساس مرحله Onboarding Workspace
- [x] Projection امن Audit Log به Activity Timeline با زمان جلالی
- [x] Client Portal current-client selection و scope assignment-based

### P2-07 — Phase 2 QA Gate
**وضعیت: ✅ انجام شد — 2026-07-25**
- [x] Full authorization matrix verification
- [x] Soft-delete behavior
- [x] Tenant isolation and client assignment tests
- [x] RTL/typecheck/build QA for Workspace screens
- [x] Full test suite: 34 tests / 95 assertions

## Definition of Done for each CRUD domain
- Migration + model + relationship
- FormRequest validation
- Authorization policy
- Controller/action separation
- Inertia typed data contract
- List/detail/create/edit/archive UI
- Empty/loading/error/success states
- Audit events
- Feature tests for happy path, invalid input and unauthorized access
- Mobile + RTL verification
