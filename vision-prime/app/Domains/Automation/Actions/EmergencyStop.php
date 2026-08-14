<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use Illuminate\Support\Facades\DB;

/**
 * توقف اضطراری خودکارسازی (D-013 فاز ۲).
 *
 * emergency_stopped_at را روی سیاست سایت فعال می‌کند (PolicyEvaluator هر تصمیم جدید را blocked می‌کند)
 * و دستورهای در صف خودکار (status=queued) را cancel می‌کند. دستورهای در انتظار تأیید انسانی
 * (pending_approval) دست نمی‌خورند؛ اما DispatchCommand به‌عنوان گارد دوم، هر dispatch را
 * تا زمان توقف مسدود می‌کند.
 */
class EmergencyStop
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(int $siteId): void
    {
        DB::transaction(function () use ($siteId): void {
            DB::table('site_automation_policies')
                ->where('site_id', $siteId)
                ->update(['emergency_stopped_at' => now(), 'updated_at' => now()]);
            DB::table('commands')
                ->where('site_id', $siteId)
                ->where('status', 'queued')
                ->update(['status' => 'cancelled', 'updated_at' => now()]);
        });

        $this->audit->handle(action: 'automation.emergency_stop_activated', after: ['site_id' => $siteId]);
    }
}
