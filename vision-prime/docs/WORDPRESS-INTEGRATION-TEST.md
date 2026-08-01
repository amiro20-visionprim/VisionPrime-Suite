# WordPress Connector Integration Test

## Start WordPress

```bash
cd vision-prime
docker compose -f docker-compose.integration.yml up -d
```

Open `http://localhost:8088`, complete the standard WordPress installation, then activate **Vision Prime Connector** from Plugins.

## Pairing test

1. In Vision Prime create Client → Project → Site.
2. Generate a pairing token for that Site.
3. In WordPress Settings → Vision Prime, set the Platform URL and Site ID; enter the pairing token.
4. Submit pairing.
5. Verify Laravel database:

```text
site_connections.status = connected
pairing_tokens.consumed_at is not null
site_connections.secret_ciphertext does not equal raw secret
Audit action: connector.paired
```

## Signed health test

1. Plugin generates timestamp, nonce and HMAC signature.
2. Plugin POSTs health metadata to `/connector/health`.
3. Verify:

```text
HTTP 200 / status=ok
last_seen_at updated
health JSON stored
connector_events contains health.checked
```

## Negative security tests

- expired pairing token → reject
- consumed token → reject
- invalid HMAC → reject
- timestamp older than five minutes → reject
- repeated nonce → reject
- disconnected connection → reject

## Cleanup

```bash
docker compose -f docker-compose.integration.yml down -v
```
