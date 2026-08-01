# Vision Prime Connector — Plugin Security QA

- [ ] All admin handlers verify `manage_options`.
- [ ] All form handlers use `check_admin_referer`.
- [ ] Platform URL escaped and sanitized.
- [ ] Pairing token is never stored after successful pairing.
- [ ] Secret is not rendered in admin UI.
- [ ] Secret is not included in local logs or REST responses.
- [ ] Content endpoint requires HMAC, timestamp and nonce.
- [ ] Replay nonce is rejected.
- [ ] Expired timestamp is rejected.
- [ ] Unknown command types are rejected when command endpoint is added.
- [ ] Plugin remains PHP 8.2 compatible.
- [ ] REST API and permalink diagnostics tested on real site.
- [ ] Pairing, signed health and content sync executed against real Laravel deployment.
