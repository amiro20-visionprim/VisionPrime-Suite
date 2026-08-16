# Vision Prime SUITE — Security Threat Model v1

## دارایی‌های حساس
- حساب و داده‌های سازمان/مشتری
- OAuth refresh token سرچ کنسول
- site-specific connector secret
- Command payload و snapshotهای WordPress
- AI provider credential و prompt/output حساس
- Audit Log و گزارش‌ها

## Threats و کنترل‌ها
| حوزه | تهدید | کنترل اجباری | تست/شواهد |
|---|---|---|---|
| Tenant isolation | مشاهده/ویرایش داده سازمان دیگر با IDOR | Organization-scoped queries، Policies، UUIDهای public | feature tests با user دو سازمان |
| Auth | credential stuffing/session hijack | rate limit، secure session/cookie، password reset امن، 2FA roadmap | auth test + config review |
| RBAC | bypass با URL/API یا UI hidden | server-side policies، permission tests | 403 matrix tests |
| Connector pairing | token سرقت/استفاده مجدد | token hash، expiry کوتاه، single-use، audit | pairing replay test |
| Connector request | forged/replayed request | HMAC، timestamp window، nonce store، constant-time compare | **signature/nonce/timestamp tests — `ReplayAttackTest` واقعی: nonce دوباره و timestamp منقضی رد می‌شوند** |
| Cross-tenant GSC | مشاهده/دستکاری property سازمان دیگر | `gsc_properties` محدود به سایت‌های همان سازمان؛ کوئری‌های organization-scoped | **`GscPropertyIsolationTest` (Feature)** |
| Cross-tenant automation | استفاده از پروفایل/پیکربندی اتوماسیون سازمان دیگر | `automation_profiles.organization_id` — پروفایل‌های سفارشی org-scoped، سیستمی جهانی | **`ProfileIsolationTest` (Feature)** |
| Rollback endpoint | بازگشت/بازگشت غیرمجاز | endpoint `/rollback` با HMAC + snapshot رمزنگاری‌شده + command در حالت مناسب | rollback integration test |
| Mutation محتوایی | تغییر نوع/هدف اشتباه (مثلاً محصول روی پست عادی) | `assert_product` در پلاگین + target validation | update_product_* test |
| Secret handling | leak در DB/log/UI | encryption/hashed secret، redaction، no secret responses | log inspection test |
| Command | اجرای تغییر غیرمجاز | allowlist، schema validation، policy re-check at dispatch، idempotency | command bypass test |
| Mutation | خرابی یا تغییر اشتباه WordPress | target validation، snapshot، rollback، rate/budget limits | rollback integration test |
| AI | key exposure/prompt injection/data leak | Laravel-only keys، template input schema، scoped context، output review, redacted logging | provider config and injection test |
| GSC OAuth | token theft/incorrect account scope | encrypted token، state validation، minimal scope، disconnect/revoke | OAuth callback tests |
| Report sharing | لینک عمومی قابل حدس یا data leak | signed/expiring links، authorization check، no index | share-link test |
| Queue/job | job duplication/retry mutation | idempotency keys، unique jobs، retry policy | duplicate job test |
| Availability | abusive import/command requests | rate limit، Horizon controls، circuit breaker/retry backoff | load/simulation checklist |

## Command Security Gate
یک command فقط اگر تمام موارد زیر true باشند قابل dispatch است:
```text
user authorized
AND command type whitelisted
AND command status approved/eligible
AND site policy permits type+risk
AND command not expired
AND emergency stop inactive
AND idempotency key unused
AND connector health acceptable
AND required snapshot exists
```

## Logging Rules
- Never log: access token, refresh token, raw connector secret، authorization header، رمز عبور، AI API key.
- Payloadها قبل از logging redact و size-limited می‌شوند.
- IP به‌صورت hash شده در Audit نگه‌داری می‌شود مگر policy حقوقی خلاف آن را الزام کند.

## Incident Basics
- Emergency stop در Site و Organization.
- امکان revoke connection secret و pair مجدد.
- امکان revoke GSC token و disconnect.
- failed commands auto-retry کور ندارند؛ retry باید type-aware و idempotent باشد.
- Security incidents با Audit correlation/request ID قابل بررسی‌اند.
