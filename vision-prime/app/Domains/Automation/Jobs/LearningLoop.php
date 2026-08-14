<?php

declare(strict_types=1);

namespace App\Domains\Automation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * حلقهٔ یادگیری (D-013 فاز ۳).
 *
 * هر روز از نتیجهٔ واقعی اجراها (executed = موفق، rolled_back = ناموفق) نرخ موفقیت
 * هر نوع تغییر را به‌ازای هر سایت محاسبه و در automation_learning_history می‌نویسد؛
 * موتور امتیازدهی در ساخت command بعدی از همین سابقه استفاده می‌کند.
 */
class LearningLoop implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public ?int $siteId = null, public int $windowDays = 30) {}

    public function handle(): void
    {
        $since = now()->subDays($this->windowDays);

        $query = DB::table('commands')
            ->whereIn('status', ['executed', 'rolled_back'])
            ->where('created_at', '>=', $since);

        if ($this->siteId !== null) {
            $query->where('site_id', $this->siteId);
        }

        $rows = $query
            ->selectRaw('site_id, type, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as successful', ['executed'])
            ->groupBy('site_id', 'type')
            ->get();

        DB::transaction(function () use ($rows, $since): void {
            foreach ($rows as $row) {
                DB::table('automation_learning_history')->updateOrInsert(
                    ['site_id' => $row->site_id, 'command_type' => $row->type],
                    [
                        'total' => (int) $row->total,
                        'successful' => (int) $row->successful,
                        'window_start' => $since,
                        'window_end' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        });
    }
}
