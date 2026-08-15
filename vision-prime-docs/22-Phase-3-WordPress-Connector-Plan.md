# Phase 3 — WordPress Connector Foundation

## Goal
Pair a WordPress site with Vision Prime securely, establish a site-specific secret, verify signed requests, and expose connection health without allowing mutations yet.

> **وضعیت (۲۰۲۶-۰۸-۱۵):** پایه تکمیل و فراتر از هدف اولیه اجرا شده — mutation ها هم فعال‌اند: `commands` (۶ نوع)، `rollback` و `product-info`. تست replay واقعی (`ReplayAttackTest`) اضافه شد.

## Atomic tasks

### P3-01 — Connector Data Contract ✅
- `site_connections`, `pairing_tokens`, `connector_nonces`, `connector_events`, `connector_sync_logs`
- Secret storage and retention classification
- Connection lifecycle: `unpaired → pairing_pending → connected → degraded → disconnected`

### P3-02 — Laravel Pairing Service ✅
- Expiring single-use pairing token
- Token hash only; raw value shown once
- Site metadata receipt
- Encrypted site-specific secret issuance
- Audit: pairing token created / consumed / failed

### P3-03 — HMAC Verification ✅
- Canonical signing payload
- timestamp window
- nonce persistence and replay rejection — **تست Feature `ReplayAttackTest`**: nonce تکراری و timestamp منقضی رد می‌شوند (HTTP 401/بدون اجرا)
- constant-time signature compare
- structured connector error contract

### P3-04 — WordPress Plugin Skeleton ✅
- PHP 8.2+ plugin structure
- settings UI
- pairing flow
- safe health endpoint
- mutation endpoints (گسترش): `commands` (update_meta_title / update_meta_description / update_content / update_product_title / update_product_description / publish_new_article) + `rollback` + `product-info`
- assert_product برای اطمینان از نوع post=product پیش از تغییر محصول
- integrity check ضد دستکاری (VP_Guard)
- **بازگشت `publish_new_article` = حذف پست ساخته‌شده** — فقط اگر متای `_vp_created_by = 'vision-prime'` باشد (هرگز پست از قبل موجود حذف نمی‌شود)
- **rollback همگام:** پاسخ endpoint شامل `restored: true/false` + نتیجهٔ واقعی است تا پلتفرم command را فقط با تأیید وردپرس `rolled_back` کند (نه ack غیرهمگام)

### P3-05 — Connection UI & Health ✅
- `/app/sites/{site}/connector`
- token creation/revoke
- connection status, last seen, plugin version, diagnostics
- reconnect / disconnect

### P3-06 — Security & Integration QA ✅
- pairing expiry/replay tests
- invalid HMAC/timestamp/nonce tests — `ReplayAttackTest`
- tenant isolation — `GscPropertyIsolationTest`, `ProfileIsolationTest`
- plugin install/pair/health checklist — E2E واقعی روی WP محلی (پورت ۸۰۸۰)
