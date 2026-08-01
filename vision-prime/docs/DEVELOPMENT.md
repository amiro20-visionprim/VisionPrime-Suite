# Vision Prime — Development Quality Commands

```bash
npm run format          # Format frontend and configuration files
npm run format:check    # Verify frontend formatting
npm run lint            # ESLint, zero warnings allowed
npm run typecheck       # Vue + TypeScript strict check
npm run build           # Production Vite build
vendor/bin/pint         # Format PHP
vendor/bin/pint --test  # Verify PHP formatting
php artisan test        # Laravel tests
```

Before opening a pull request, all commands above must pass. PHP and frontend changes must stay within the current atomic task scope.
