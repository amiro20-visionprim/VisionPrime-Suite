<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use Illuminate\Support\Facades\DB;

/**
 * رفع توقف اضطراری: emergency_stopped_at را پاک می‌کند و خودکارسازی طبق سیاست از سر گرفته می‌شود.
 */
class ResumeAutomation
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(int $siteId): void
    {
        DB::table('site_automation_policies')
            ->where('site_id', $siteId)
            ->update(['emergency_stopped_at' => null, 'updated_at' => now()]);

        $this->audit->handle(action: 'automation.emergency_stop_released', after: ['site_id' => $siteId]);
    }
}
