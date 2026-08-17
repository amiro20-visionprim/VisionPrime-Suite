<?php

declare(strict_types=1);

namespace App\Domains\Platform\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use Illuminate\Support\Facades\DB;

/**
 * توقف اضطراری سراسری (بالای EmergencyStop که per-site است).
 * تمام سایت‌های یک سازمان (یا همهٔ سازمان‌ها) متوقف می‌شوند و دستورهای
 * در صف خودکار cancel می‌شوند. با تأیید دوتایی در UI + audit کامل.
 */
class PlatformEmergencyStop
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(?int $organizationId = null, string $reason = ''): int
    {
        return DB::transaction(function () use ($organizationId, $reason): int {
            $siteQuery = DB::table('sites');
            if ($organizationId !== null) {
                $siteQuery->where('organization_id', $organizationId);
            }
            $siteIds = $siteQuery->pluck('id');

            $updated = DB::table('site_automation_policies')
                ->whereIn('site_id', $siteIds)
                ->update([
                    'emergency_stopped_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('commands')
                ->whereIn('site_id', $siteIds)
                ->where('status', 'queued')
                ->update(['status' => 'cancelled', 'updated_at' => now()]);

            $this->audit->handle(
                action: 'platform.emergency_stop_activated',
                after: [
                    'organization_id' => $organizationId,
                    'sites_affected' => $siteIds->count(),
                    'reason' => $reason,
                ],
                organization: null,
                source: 'platform',
            );

            return (int) $updated;
        });
    }
}
