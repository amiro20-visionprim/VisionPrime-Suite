# Vision Prime

Premium SEO intelligence and WordPress growth operations platform.

## Technology

- Laravel 12 / PHP 8.3+
- Vue 3 / Inertia.js 2 / TypeScript
- Tailwind CSS 4
- PostgreSQL 17
- Redis / Laravel Horizon

## Local setup

```bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
php artisan migrate --seed
npm run dev
php artisan serve
```

For local tests, SQLite is supported. Production should use PostgreSQL and Redis as documented in `.env.example`.

## Quality commands

```bash
npm run format:check
npm run lint
npm run typecheck
npm run build
php vendor/bin/pint --test
php artisan test
```

## Product and architecture documents

The source-of-truth planning documents are in `../vision-prime-docs/` at the workspace root. Development-specific instructions are in `docs/DEVELOPMENT.md`.
