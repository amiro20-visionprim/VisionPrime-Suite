# Vision Prime — Migration Ordering & Data Classification

## 1) Migration ordering
Migrationها کوچک، reversible تا حد ممکن، و به ترتیب dependency اجرا می‌شوند. نام migration باید domain را روشن کند.

### Wave A — Platform & Identity
1. Laravel users/password reset/session tables
2. organizations
3. roles, permissions, role_permissions
4. memberships
5. organization settings / entitlements
6. audit_logs

### Wave B — Workspace
7. clients
8. projects
9. sites
10. site_user_assignments (در صورت نیاز به scope دقیق‌تر از membership)

### Wave C — Connector & Content
11. site_connections
12. pairing_tokens
13. connector_nonces
14. connector_events
15. sync_runs
16. sync_run_items
17. url_profiles
18. content_snapshots

### Wave D — GSC & Intelligence
19. gsc_accounts
20. gsc_properties
21. gsc_import_runs
22. gsc_page_metrics
23. gsc_query_metrics
24. gsc_query_page_metrics
25. keyword_insights
26. intent_classifications
27. opportunities
28. opportunity_factors

### Wave E — Optimization & Workflow
29. money_page_audits
30. money_page_issues
31. conversion_risks
32. conversion_risk_factors
33. recommendations
34. ai_provider_settings
35. ai_prompt_templates
36. ai_generations
37. ai_generation_versions
38. ai_usage_logs
39. review_items
40. review_decisions

### Wave F — Automation, Reporting, Operations
41. site_automation_policies
42. commands
43. command_approvals
44. command_execution_logs
45. rollback_snapshots
46. reports
47. impact_events

## 2) Data Classification
| Class | نمونه | Storage rule | Log rule | Access |
|---|---|---|---|---|
| Public | نام محصول، صفحات عمومی | plaintext | normal | public |
| Internal | config ظاهری، metadata غیرحساس | DB plaintext | limited | org members by policy |
| Confidential | client contacts، content snapshots، report content | DB with access policy; encrypt selected fields where needed | redact/minimize | assigned scope |
| Secret | OAuth tokens، connector secret، AI key | encrypted/hashed only | never log | service-only/admin restricted |
| Restricted | password hash، reset token، security evidence | platform-managed secure storage | never log raw | platform security only |

## 3) Field-level rules
- Password: Laravel hash only.
- Pairing token: hash only; original فقط یک‌بار در UI نشان داده شود.
- Connector secret: encrypted اگر Laravel باید آن را برای outbound signing بازیابی کند؛ نسخه raw هرگز در log/UI دیده نشود.
- OAuth refresh token: encrypted at rest با Laravel encrypted casts یا service encryption.
- AI key: encrypted at rest؛ masked UI مثل `••••••ab12`.
- Snapshot: اگر content مشتری حساس است، encrypted blob/ref و access-controlled storage.
- IP: hash + optional short retention؛ raw IP فقط اگر requirement حقوقی صریح وجود دارد.

## 4) Retention defaults
| Data | Default | Policy |
|---|---|---|
| Audit logs | 24 ماه | non-destructive; export for enterprise |
| Command execution logs | 24 ماه | redact payloads |
| Connector nonce | تا expiry + cleanup | 15 دقیقه default |
| Pairing token | تا expiry/consumed + 30 روز metadata | raw never stored |
| Sync run details | 12 ماه | summary longer if needed |
| Content snapshots | 12 ماه یا configurable | per enterprise policy |
| GSC daily metrics | طول عمر قرارداد + 12 ماه | aggregate before archive if needed |
| AI generation content | 12 ماه یا site policy | archive/delete on request where allowed |
| Reports | طول عمر قرارداد + 24 ماه | client export available |

## 5) Deletion & Export
- حذف Client/Site ابتدا soft-delete و grace period دارد؛ destructive deletion نیازمند Agency Admin و audit reason است.
- Export داده برای سازمان/مشتری باید asynchronous job و signed download link باشد.
- حذف/retention هرگز نباید integrity AuditLog را بشکند؛ audit record می‌تواند subject reference anonymized داشته باشد.
