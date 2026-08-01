# Vision Prime — Production Launch Checklist

## Application
- [ ] `APP_ENV=production`, debug disabled, HTTPS enforced
- [ ] app key and encryption keys backed up securely
- [ ] production PostgreSQL and Redis configured
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan config:cache`, `route:cache`, `view:cache`
- [ ] Horizon supervisor running and failed job procedure documented

## Security
- [ ] OAuth client credentials set only via environment
- [ ] AI provider keys encrypted and unavailable to WordPress
- [ ] connector secrets encrypted
- [ ] HMAC/timestamp/nonce tests pass
- [ ] command approval and emergency-stop tests pass
- [ ] audit retention policy configured

## Operations
- [ ] daily PostgreSQL backup and restore drill
- [ ] Redis persistence strategy
- [ ] log rotation and error alerting
- [ ] queue retry policy / failed job workflow
- [ ] deployment rollback plan

## WordPress
- [ ] plugin installed on test site
- [ ] pairing, signed health, content sync tested
- [ ] REST API, permalink, PHP and WP diagnostics reviewed
- [ ] no secret appears in plugin UI or logs

## Product QA
- [ ] marketing, admin and client portal smoke test
- [ ] RTL / mixed LTR content tested
- [ ] mobile check: 360px / 768px / 1024px
- [ ] empty, loading, error and success states tested
- [ ] demo seed organization/client/project/site available
