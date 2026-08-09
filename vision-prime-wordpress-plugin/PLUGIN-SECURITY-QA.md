# Vision Prime Connector — Plugin Security QA

Validated on a real local WordPress (SQLite, WP 7.0.3) against the real Laravel app over HTTP on 2026-08-09.

- [x] All admin handlers verify `manage_options`.
- [x] All form handlers use `check_admin_referer`.
- [x] Platform URL escaped and sanitized.
- [x] Pairing token is never stored after successful pairing.
- [x] Secret is not rendered in admin UI.
- [x] Secret is not included in local logs or REST responses.
- [x] Content endpoint requires HMAC, timestamp and nonce.
- [x] Replay nonce is rejected.
- [x] Expired timestamp is rejected.
- [x] Unknown command types are rejected when command endpoint is added.
- [x] Plugin remains PHP 8.2 compatible.
- [x] REST API and permalink diagnostics tested on real site.
- [x] Pairing, signed health and content sync executed against real Laravel deployment.
- [x] Commands endpoint (`POST /vision-prime/v1/commands`) signed, idempotent, executes and reports back.
- [x] Result callback (`/connector/command-result`) signed and verified by the platform.

## Notes (v0.2.0)

- Signature paths: app→plugin requests are signed with the route as matched (leading slash,
  e.g. `/vision-prime/v1/content`); plugin→app requests must sign without a leading slash
  (e.g. `connector/health`) to match Laravel's `request->path()`.
- The plugin must never block its `/commands` response on the result callback; the callback is
  sent with `blocking => false` to avoid a deadlock with the platform's synchronous dispatch.
- Command payloads should carry `post_id` (or `url`) plus the new value(s); unknown types fail
  safely and report `status: failed` back to the platform.
