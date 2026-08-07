# Vision Prime — Ubuntu 24.04 Deployment Runbook

> **Fast path:** the Docker Compose stack (`docker-compose.production.yml`) is the
> supported deployment. Run `./deploy.sh production` after configuring
> `.env.production` (copy from `.env.production.template`).

## Docker deployment (`./deploy.sh production`)

The script performs: requirements check (Docker + `docker compose` v2),
self-signed certificate bootstrap (if `docker/nginx/ssl/*.pem` missing — replace
with Let's Encrypt for real domains), image build (includes the frontend
`npm ci && vite build` stage inside the image), migrations + `RolePermissionSeeder`,
config/route/view caching, service startup, and an `https://<domain>/up` health check.

Required on the host:
- Docker Engine + the `docker compose` plugin (v2)
- Ports 80/443 reachable; DNS A record → server IP
- `.env.production` with `APP_DOMAIN`, `APP_URL`, `APP_KEY`
  (`php artisan key:generate --show`), `DB_PASSWORD`, `REDIS_PASSWORD`, GSC/Mail values

## Manual (non-Docker) build
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Required services (non-Docker path)
- Nginx + PHP-FPM 8.3+
- PostgreSQL 17
- Redis
- Laravel Horizon under Supervisor

## Horizon Supervisor command
```bash
php artisan horizon
```
Restart after deployment:
```bash
php artisan horizon:terminate
```

## Required cron (non-Docker path; the compose stack ships a scheduler service)
```bash
* * * * * cd /var/www/vision-prime && php artisan schedule:run >> /dev/null 2>&1
```

## Health checks
```bash
curl -f https://your-domain/up   # returns {"status":"ok"} (DB check)
php artisan horizon:status
php artisan queue:failed
```

## Rollback
1. Put application in maintenance mode.
2. Restore previous release symlink/code.
3. Roll back only reversible migrations; otherwise restore PostgreSQL backup.
4. `php artisan optimize:clear` then cache config/routes/views.
5. `php artisan horizon:terminate`.
6. Exit maintenance mode and run `/up` health check.

## Backups
- Daily PostgreSQL encrypted backup.
- Retain per operational policy.
- Perform a restore drill before private launch.
