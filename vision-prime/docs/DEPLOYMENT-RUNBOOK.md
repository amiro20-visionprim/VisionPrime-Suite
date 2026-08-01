# Vision Prime — Ubuntu 24.04 Deployment Runbook

## Build
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Required services
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

## Required cron
```bash
* * * * * cd /var/www/vision-prime && php artisan schedule:run >> /dev/null 2>&1
```

## Health checks
```bash
curl -f https://your-domain/up
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
