<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e(str_starts_with(app()->getLocale(), 'fa') ? 'rtl' : 'ltr'); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia><?php echo e(config('app.name', 'Vision Prime SUITE')); ?></title>
        <script>
            // Anti-FOUC: apply saved/system theme before first paint (mirrors lib/theme.ts)
            // پیش‌فرض «تاریک» است — کاربر با توگل می‌تواند روشن را انتخاب کند.
            (function () {
                try {
                    var stored = window.localStorage.getItem('suite-theme') || 'dark';
                    var dark =
                        stored === 'dark' ||
                        (stored !== 'light' &&
                            window.matchMedia('(prefers-color-scheme: dark)').matches);
                    if (dark) document.documentElement.classList.add('dark');
                    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
                } catch (e) {}
            })();
        </script>
        <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.ts']); ?>
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
    </head>
    <body class="bg-canvas font-sans text-ink" dir="<?php echo e(str_starts_with(app()->getLocale(), 'fa') ? 'rtl' : 'ltr'); ?>">
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } elseif (config('inertia.use_script_element_for_initial_page')) { ?><script data-page="app" type="application/json"><?php echo json_encode($page); ?></script><div id="app"></div><?php } else { ?><div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div><?php } ?>
    </body>
</html>
<?php /**PATH C:\Users\Armita\Documents\Vsionprime SUITE\workspace-arena-suite\vision-prime\resources\views/app.blade.php ENDPATH**/ ?>