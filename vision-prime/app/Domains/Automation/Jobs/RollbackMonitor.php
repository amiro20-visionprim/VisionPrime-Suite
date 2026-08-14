<?php

declare(strict_types=1);

namespace App\Domains\Automation\Jobs;

use App\Domains\Automation\Actions\RecordMetricDropAlert;
use App\Domains\Automation\Actions\RollbackCommand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * مانیتور بازگشت و هشدار (D-013 فاز ۳).
 *
 * هر ۶ ساعت، command های اجراشده در پنجرهٔ سنجش را با baseline هفت‌روزهٔ قبل از انتشار
 * (از gsc_page_metrics واقعی) مقایسه می‌کند:
 *  - R1 با افت ≥۲۰٪ → فقط هشدار (RecordMetricDropAlert)، بدون rollback خودکار
 *  - R3 با افت ≥۲۰٪ و پروفایل auto_rollback → RollbackCommand
 * دادهٔ کافی نباشد (baseline یا دورهٔ پس از انتشار) → رد می‌شود تا اجرای بعدی.
 */
class RollbackMonitor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    /** آستانهٔ افت (۰٫۸ = افت ۲۰٪) */
    public const DROP_THRESHOLD = 0.8;

    public function handle(RollbackCommand $rollback, RecordMetricDropAlert $alert): void
    {
        foreach ($this->r1Candidates() as $candidate) {
            $drop = $this->detectDrop($candidate);
            if ($drop !== null && $drop < self::DROP_THRESHOLD) {
                $alert->handle($candidate, $drop);
            }
        }

        foreach ($this->r3Candidates() as $candidate) {
            $drop = $this->detectDrop($candidate);
            if ($drop !== null && $drop < self::DROP_THRESHOLD) {
                $rollback->handle((int) $candidate->id);
            }
        }
    }

    /** R1 اجراشده با پروفایلِ دارای alert_level — فقط برای هشدار. */
    private function r1Candidates(): array
    {
        $windowHours = 336;

        return DB::table('commands as c')
            ->join('site_automation_policies as p', 'p.site_id', '=', 'c.site_id')
            ->join('automation_profiles as ap', 'ap.id', '=', 'p.active_profile_id')
            ->where('c.status', 'executed')
            ->where('c.risk_tier', 'R1')
            ->where('ap.alert_level', '!=', 'none')
            ->whereNotNull('c.published_at')
            ->where('c.published_at', '>=', now()->subHours($windowHours))
            ->select('c.id', 'c.site_id', 'c.type', 'c.published_at', 'c.payload')
            ->get()
            ->all();
    }

    /** R3 اجراشده با پروفایل auto_rollback — برای بازگشت خودکار. */
    private function r3Candidates(): array
    {
        $windowHours = 336;

        return DB::table('commands as c')
            ->join('site_automation_policies as p', 'p.site_id', '=', 'c.site_id')
            ->join('automation_profiles as ap', 'ap.id', '=', 'p.active_profile_id')
            ->where('c.status', 'executed')
            ->where('c.risk_tier', 'R3')
            ->where('ap.auto_rollback', true)
            ->whereNotNull('c.published_at')
            ->where('c.published_at', '>=', now()->subHours($windowHours))
            ->select('c.id', 'c.site_id', 'c.type', 'c.published_at', 'c.payload')
            ->get()
            ->all();
    }

    /** میانگین کلیک روزانهٔ URL در بازهٔ داده‌شده. */
    private function avgClicks(int $siteId, string $url, string $from, string $to): ?float
    {
        $property = DB::table('gsc_properties')->where('site_id', $siteId)->first();
        if ($property === null) {
            return null;
        }

        $row = DB::table('gsc_page_metrics')
            ->where('gsc_property_id', $property->id)
            ->where('page_url', $url)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('SUM(clicks) as clicks, COUNT(DISTINCT date) as days')
            ->first();

        if ($row === null || (int) $row->days === 0) {
            return null;
        }

        return (float) $row->clicks / (int) $row->days;
    }

    /** @return float|null  نسبت current/baseline؛ null اگر داده ناکافی باشد. */
    private function detectDrop(object $command): ?float
    {
        $payload = json_decode((string) $command->payload, true);
        $url = is_array($payload) && isset($payload['url']) ? (string) $payload['url'] : null;
        if ($url === null || $url === '') {
            return null;
        }

        $published = Carbon::parse($command->published_at);
        $baseline = $this->avgClicks((int) $command->site_id, $url, $published->copy()->subDays(7)->toDateString(), $published->copy()->subDay()->toDateString());
        $current = $this->avgClicks((int) $command->site_id, $url, $published->toDateString(), $published->copy()->addDays(7)->toDateString());

        if ($baseline === null || $current === null || $baseline <= 0) {
            return null;
        }

        return $current / $baseline;
    }
}
