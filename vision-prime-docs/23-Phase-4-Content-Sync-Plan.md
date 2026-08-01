# Phase 4 — Content Sync & URL Profiles

## Atomic delivery

### P4-01 Content Sync Data Contract
- `sync_runs`, `sync_run_items`, `url_profiles`, `content_snapshots`
- lifecycle: queued → running → completed / partially_completed / failed
- content hash and snapshot deduplication

### P4-02 Secure WordPress Content Endpoint
- paginated posts/pages list
- title, URL, slug, type, status, modified date
- meta title, meta description, headings, word count, content hash
- HMAC-signed request requirement

### P4-03 Laravel Queue Sync
- Redis queue job
- pagination cursor
- idempotent upsert URL profile
- snapshot only when content hash changes
- failure logging and retry strategy

### P4-04 Content & URL UI
- site sync page
- URL profile list/detail
- snapshot history
- sync logs, errors and last sync

### P4-05 QA Gate
- changed/unchanged content tests
- failed page item test
- tenant scope
- queue / retry tests
