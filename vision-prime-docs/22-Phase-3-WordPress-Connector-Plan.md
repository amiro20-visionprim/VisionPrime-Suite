# Phase 3 — WordPress Connector Foundation

## Goal
Pair a WordPress site with Vision Prime securely, establish a site-specific secret, verify signed requests, and expose connection health without allowing mutations yet.

## Atomic tasks

### P3-01 — Connector Data Contract
- `site_connections`, `pairing_tokens`, `connector_nonces`, `connector_events`, `connector_sync_logs`
- Secret storage and retention classification
- Connection lifecycle: `unpaired → pairing_pending → connected → degraded → disconnected`

### P3-02 — Laravel Pairing Service
- Expiring single-use pairing token
- Token hash only; raw value shown once
- Site metadata receipt
- Encrypted site-specific secret issuance
- Audit: pairing token created / consumed / failed

### P3-03 — HMAC Verification
- Canonical signing payload
- timestamp window
- nonce persistence and replay rejection
- constant-time signature compare
- structured connector error contract

### P3-04 — WordPress Plugin Skeleton
- PHP 8.2+ plugin structure
- settings UI
- pairing flow
- safe health endpoint
- no AI keys; no mutation commands

### P3-05 — Connection UI & Health
- `/app/sites/{site}/connector`
- token creation/revoke
- connection status, last seen, plugin version, diagnostics
- reconnect / disconnect

### P3-06 — Security & Integration QA
- pairing expiry/replay tests
- invalid HMAC/timestamp/nonce tests
- tenant isolation
- plugin install/pair/health checklist
