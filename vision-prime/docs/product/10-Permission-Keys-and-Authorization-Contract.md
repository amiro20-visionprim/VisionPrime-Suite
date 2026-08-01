# Vision Prime — Permission Keys & Authorization Contract

## Naming convention
`domain.action.scope`

- domain: organization, member, client, project, site, connector, gsc, intelligence, recommendation, ai, review, command, rollback, report, audit, billing
- action: view, create, update, delete, manage, approve, execute, export, stop
- scope: own, assigned, organization, platform در صورت نیاز

## Permission Catalog v1
```text
organization.manage.organization
member.view.organization
member.manage.organization
role.manage.organization
client.view.organization
client.manage.organization
project.view.organization
project.manage.organization
site.view.organization
site.manage.organization
site.view.assigned
connector.view.assigned
connector.manage.assigned
connector.pair.assigned
connector.disconnect.assigned
gsc.view.assigned
gsc.manage.assigned
gsc.import.assigned
intelligence.view.assigned
intelligence.override.assigned
recommendation.view.assigned
recommendation.create.assigned
recommendation.manage.assigned
ai.use.assigned
ai.template.manage.organization
ai.provider.manage.organization
review.view.assigned
review.decide.assigned
command.view.assigned
command.create.assigned
command.approve.assigned
command.dispatch.assigned
command.execute.assigned
command.stop.assigned
rollback.view.assigned
rollback.execute.assigned
automation_policy.view.assigned
automation_policy.manage.assigned
report.view.assigned
report.generate.assigned
report.publish.assigned
report.export.assigned
audit.view.organization
billing.view.organization
billing.manage.organization
```

## Authorization Contract
1. **Route middleware فقط اولین لایه است.** تمام read/write حساس در Laravel Policy یا service authorization دوباره بررسی می‌شود.
2. هر Policy ابتدا Organization scope، سپس entity assignment و سپس permission را بررسی می‌کند.
3. API/Controller نباید role name را مستقیم شرط کند، مگر در platform-only operations.
4. Frontend صرفاً با `can` props برای UX دکمه را نمایش/مخفی می‌کند؛ این یک کنترل امنیتی نیست.
5. هر bypass نیازمند `authorization_reason` و AuditLog است.

## نقش‌های پیش‌فرض به صورت Permission Bundle
| Role | Permission Bundle |
|---|---|
| Agency Admin | تمام permissionهای organization به‌جز platform-only |
| SEO Manager | site/gsc/intelligence/recommendation/report برای Siteهای assigned؛ command فقط طبق policy |
| Content Manager | view intelligence، مدیریت recommendationهای محتوایی، AI use؛ بدون connector/command |
| Expert Reviewer | view/decide reviewهای assigned؛ rollback/command فقط در policy صریح |
| Developer | connector، site health و command executionهای مجاز؛ بدون AI provider/billing |
| Client Viewer | report و client-safe projection در scope assigned |
| Client Approver | Client Viewer + review.decide روی itemهای client-visible |

## Test Matrix حداقلی
- Client Viewer با URL مستقیم به `/app/gsc/queries` پاسخ 403 می‌گیرد.
- SEO Manager نمی‌تواند Site خارج از assignment را mutate کند.
- Developer نمی‌تواند Automation Level را ارتقا دهد.
- Reviewer نمی‌تواند itemی که به او assignment ندارد approve کند، مگر organization policy اجازه داده باشد.
- Agency Admin تغییر Role را انجام می‌دهد و AuditLog before/after ایجاد می‌شود.
- Command execute بدون approval/policy مناسب حتی با permission ظاهری رد می‌شود.
