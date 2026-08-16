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
 * تقویم محتوایی — آزادسازی کامندهای موعدرسیده (هر دقیقه در schedule).
 *
 * کامندهای publish_new_article با status=scheduled که scheduled_for به آن‌ها رسیده را
 * به pending_approval برمی‌گرداند و بلافاصله از AutoPublish عبور می‌دهد؛ یعنی انتشار در
 * لحظهٔ موعد با Policy جاری تصمیم گرفته می‌شود (خودکار یا انتظار تأیید انسانی).
 * scheduled_for به‌عنوان رکورد تاریخ برنامه‌ریزی‌شده باقی می‌ماند.
 */
class ReleaseScheduledCommands implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function handle(AutoPublish $autoPublish): void
    {
        $ids = DB::table('commands')
            ->where('status', 'scheduled')
            ->where('scheduled_for', '<=', now())
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            DB::table('commands')->where('id', $id)->update([
                'status' => 'pending_approval',
                'updated_at' => now(),
            ]);

            $autoPublish->handle((int) $id);
        }
    }
}
