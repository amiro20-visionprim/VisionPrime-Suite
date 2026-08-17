<?php

declare(strict_types=1);

use App\Domains\Automation\Jobs\LearningLoop;
use App\Domains\Automation\Jobs\ProcessQueuedCommands;
use App\Domains\Automation\Jobs\ReleaseScheduledCommands;
use App\Domains\Automation\Jobs\RemindScheduledPublishes;
use App\Domains\Automation\Jobs\RollbackMonitor;
use App\Domains\Platform\Jobs\CollectPlatformEvents;
use App\Domains\Platform\Jobs\DunningJob;
use App\Domains\Platform\Jobs\SendDailyBriefing;
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

// D-013 فاز ۳ — حلقهٔ یادگیری: نرخ موفقیت هر نوع تغییر → automation_learning_history
Schedule::job(new LearningLoop)
    ->dailyAt('05:00')
    ->timezone('Asia/Tehran')
    ->onOneServer();

// D-013 فاز ۳ — مانیتور بازگشت خودکار R3 (مقایسه با baseline هر ۶ ساعت)
Schedule::job(new RollbackMonitor)
    ->everySixHours()
    ->timezone('Asia/Tehran')
    ->onOneServer();

// تقویم محتوایی — آزادسازی پیش‌نویس‌های زمان‌بندی‌شده در لحظهٔ موعد (هر دقیقه)
Schedule::job(new ReleaseScheduledCommands)
    ->everyMinute()
    ->timezone('Asia/Tehran')
    ->onOneServer();

// تقویم محتوایی — یادآوری موعد انتشار به اعضای فعال سازمان (یک روز قبل، روزانه ۰۹:۰۰)
Schedule::job(new RemindScheduledPublishes)
    ->dailyAt('09:00')
    ->timezone('Asia/Tehran')
    ->onOneServer();

// D-013 — پردازش صف خودکار: retry دستورهای به‌تأخیرافتاده (حلقهٔ delay → retry)
Schedule::job(new ProcessQueuedCommands)
    ->everyThirtyMinutes()
    ->timezone('Asia/Tehran')
    ->onOneServer();

// فاز E — حسگر روزانهٔ پلتفرم (Triage Engine): اسکن وضعیت‌ها و ثبت رویداد
Schedule::job(new CollectPlatformEvents)
    ->dailyAt('06:00')
    ->timezone('Asia/Tehran')
    ->onOneServer();

// فاز E — گزارش روزانهٔ هوشمند (Daily Briefing) برای مدیر ارشد
Schedule::job(new SendDailyBriefing)
    ->dailyAt('07:30')
    ->timezone('Asia/Tehran')
    ->onOneServer();

// فاز E — Dunning: پیگیری فاکتورهای معوق و تعلیق بعد از grace period
Schedule::job(new DunningJob)
    ->dailyAt('08:00')
    ->timezone('Asia/Tehran')
    ->onOneServer();

// فاز E — بکاپ روزانهٔ پایگاه داده (نگهداری ۷ نسخه)
Schedule::command('platform:backup-db')
    ->dailyAt('02:00')
    ->timezone('Asia/Tehran')
    ->onOneServer();
