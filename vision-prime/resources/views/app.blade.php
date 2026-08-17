<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ str_starts_with(app()->getLocale(), 'fa') ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Vision Prime SUITE') }}</title>
        <script>
            // Anti-FOUC: apply saved/system theme before first paint (mirrors lib/theme.ts)
            (function () {
                try {
                    var stored = window.localStorage.getItem('suite-theme') || 'system';
                    var dark =
                        stored === 'dark' ||
                        (stored !== 'light' &&
                            window.matchMedia('(prefers-color-scheme: dark)').matches);
                    if (dark) document.documentElement.classList.add('dark');
                    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
                } catch (e) {}
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="bg-canvas font-sans text-ink" dir="{{ str_starts_with(app()->getLocale(), 'fa') ? 'rtl' : 'ltr' }}">
        @inertia
    </body>
</html>
