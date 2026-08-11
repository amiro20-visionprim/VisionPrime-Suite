<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily Search Console import for every site with a connected GSC property.
// Runs via `php artisan schedule:work` (or Windows Task Scheduler calling
// `php artisan schedule:run` every minute). Search Console data is delayed
// 2-3 days, so a single daily run at 04:30 keeps the intelligence layer fresh.
Schedule::command('gsc:import --days=28')
    ->dailyAt('04:30')
    ->timezone('Asia/Tehran')
    ->onOneServer();
