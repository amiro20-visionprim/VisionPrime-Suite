<?php

declare(strict_types=1);

namespace App\Domains\Automation\Jobs;

use App\Domains\Automation\Actions\AutoPublish;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * پردازش صف خودکار (D-013 — بستن حلقهٔ delay → retry).
 *
 * دستورهای status=queued (که به دلیل سقف روزانه/پنجرهٔ اجرا به تأخیر افتاده‌اند) را
 * دوباره از AutoPublish عبور می‌دهد تا Policy فعلی را دوباره ارزیابی کند: اگر اکنون
 * داخل پنجره/زیر سقف و بالای آستانه باشد → انتشار خودکار؛ در غیر این صورت queued می‌ماند
 * یا در صورت رد، cancel می‌شود. دستورهای منقضی‌شده مستقیماً cancel می‌شوند.
 */
class ProcessQueuedCommands implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function handle(AutoPublish $autoPublish): void
    {
        DB::table('commands')
            ->where('status', 'queued')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'cancelled', 'updated_at' => now()]);

        $ids = DB::table('commands')
            ->where('status', 'queued')
            ->where('expires_at', '>', now())
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            $autoPublish->handle((int) $id);
        }
    }
}
