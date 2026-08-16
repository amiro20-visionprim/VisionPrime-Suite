# Vision Prime SUITE — API, Events & State Contracts

## 1) قرارداد عمومی Backend
- Inertia برای صفحات App و Client Portal استفاده می‌شود.
- JSON API فقط برای connector، integration callback/webhook و در آینده public API استفاده می‌شود.
- تمام mutationها validation و authorization server-side دارند.
- statusها enum ثابت و shared TypeScript type دارند؛ stringهای پراکنده مجاز نیستند.

## 2) Error Contract برای JSON endpoints
```json
{
  "code": "CONNECTOR_SIGNATURE_INVALID",
  "message": "امضای درخواست معتبر نیست.",
  "details": {},
  "request_id": "..."
}
```

### دسته‌های Code
- `VALIDATION_*`
- `AUTHENTICATION_*`
- `AUTHORIZATION_*`
- `CONNECTOR_*`
- `GSC_*`
- `SYNC_*`
- `AI_*`
- `COMMAND_*`
- `RATE_LIMIT_*`
- `INTERNAL_*`

## 3) Pagination / Filter / Sort
```text
?page=1&per_page=25&sort=-created_at&filter[status]=open&filter[site_id]=...
```
- حد per_page مشخص و محدود است.
- sort fieldها whitelist می‌شوند.
- filterها typed و documented هستند.
- response list شامل `data`, `meta`, `links` است.

## 3.5) Connector Endpoints (پلاگین وردپرس)

| Endpoint | Method | Auth | کاربرد |
|---|---|---|---|
| `/wp-json/vision-prime/v1/health` | GET | — | health عمومی (نسخهٔ پلاگین، tamper check) |
| `/wp-json/vision-prime/v1/content` | GET | HMAC | لیست پست‌ها/صفحات (sync محتوا) |
| `/wp-json/vision-prime/v1/commands` | POST | HMAC | اجرای command (meta/content/product) |
| `/wp-json/vision-prime/v1/rollback` | POST | HMAC | بازگشت snapshot قبلی (متا/تایتل/محتوا/حذف پست publish_new_article) |
| `/wp-json/vision-prime/v1/product-info` | POST | HMAC | اطلاعات واقعی محصول ووکامرس (قیمت/موجودی/وضعیت — با post_id یا اسلاگ) |

همهٔ درخواست‌های امضاشده: HMAC-SHA256 + timestamp window + nonce یک‌بارمصرف (replay rejection) + constant-time compare. امضای نادرست/تکرار nonce/timestamp منقضی → خطای ساخت‌یافته CONNECTOR_* بدون اجرا.

## 4) Lifecycle State Machines

### Site connection
`unpaired → pairing_pending → connected → degraded → disconnected`

### Sync run
`queued → running → completed | partially_completed | failed | cancelled`

### Review item
`draft → pending_review → approved | rejected | changes_requested | archived`

### Recommendation
`draft → active → in_review → approved → implemented | rejected | archived`

### Command
`draft → pending_approval → approved → queued → dispatched → executed`

مسیر خودکار (D-013): وقتی Policy مجاز باشد، تأیید با `reviewer_type=system` + `policy_snapshot` ثبت می‌شود و `decision_source=policy`؛ وضعیت همچنان `approved → … → executed` است و `published_at` پر می‌شود (برای publish_new_article). مسیر غیرخودکار با تأیید انسانی `decision_source=manual`.

**تقویم محتوایی:** `pending_approval → scheduled` (با `scheduled_for`) → در موعد، `ReleaseScheduledCommands` (هر دقیقه) کامند را به `pending_approval` برمی‌گرداند و از AutoPublish عبور می‌دهد (تصمیم نهایی در لحظهٔ موعد با Policy جاری). **انتشار فوری** (`action=publish_now`): موعد = همین لحظه + عبور فوری از AutoPublish. لغو زمان‌بندی → بازگشت به `pending_approval` و `scheduled_for=null`. `scheduled_for` به‌عنوان رکورد تاریخ برنامه‌ریزی پس از انتشار باقی می‌ماند. موعد پیش‌نویس هنگام ساخت از تقویم روی `ai_generations.scheduled_for` ذخیره می‌شود و در تأیید بازبین به کامند منتقل می‌گردد.

Terminal/exception states:
`failed | expired | cancelled | rolled_back | policy_denied`

بازگشت خودکار R3: `executed → rolled_back` با snapshot کامل (رستور بدون‌اتلاف از طریق endpoint `/rollback` پلاگین).

### Report
`draft → generating → ready → published | failed | archived`

## 5) Domain Events
| Event | Producer | Consumer نمونه |
|---|---|---|
| SiteCreated | Site domain | onboarding checklist, AuditLog |
| ConnectorPaired | Connector domain | health check dispatch, AuditLog |
| SyncCompleted | Sync domain | URL profile update, dashboard metrics |
| GscImportCompleted | GSC domain | intelligence recompute |
| OpportunityCalculated | Intelligence domain | notification/ranking refresh |
| RecommendationCreated | Recommendation domain | review policy evaluator |
| ReviewDecided | Review domain | command eligibility / notification |
| AutomationPolicyChanged | Governance domain | audit, pending command re-evaluation |
| CommandExecuted | Command domain | impact baseline, activity feed |
| CommandFailed | Command domain | alert, retry decision |
| CommandPublishScheduled | Command domain | calendar refresh, audit (`command.publish_scheduled`) |
| CommandPublishScheduleCancelled | Command domain | calendar refresh, audit (`command.publish_schedule_cancelled`) |
| ScheduledPublishReminder | Automation domain | یک روز قبل از موعد → اعلان database به اعضای فعال سازمان (job روزانه ۰۹:۰۰) |
| ReportPublished | Reporting domain | client notification |

## 6) Audit Event Contract
تمام رخدادهای حساس حداقل شامل موارد زیرند:
```text
organization_id, actor_type, actor_id, action,
subject_type, subject_id, before, after,
source (web/api/connector/job), request_id, occurred_at
```
Payload باید redact شود؛ token، secret، محتوای حساس و credential ذخیره نمی‌شود.

## 7) Product Analytics Events
Eventها از Audit جدا هستند. هدف analytics، فهم adoption و journey است، نه اثبات امنیتی.

حداقل properties مشترک:
```text
organization_id, user_id (if applicable), client_id, project_id,
site_id, locale, app_surface, occurred_at
```

Event catalog در `02-End-to-End-User-Journeys.md` شروع شده و با هر Flow تکمیل می‌شود.
