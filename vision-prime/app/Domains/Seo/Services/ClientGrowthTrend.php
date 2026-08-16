<?php

declare(strict_types=1);

namespace App\Domains\Seo\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * دادهٔ روند و KPI رشد از سرچ کنسول واقعی (gsc_page_metrics) — مشترک بین
 * خانه و «رشد من» در پنل مشتری. هرگز تخمین نمی‌زند: بدون داده → null.
 */
class ClientGrowthTrend
{
    /**
     * @param  Collection<int, int>  $siteIds
     * @return array{0: Collection<int, array<string, int|float|string>>, 1: array<string, array<string, int|float|null>>|null}
     */
    public function forSites(Collection $siteIds): array
    {
        $propertyIds = DB::table('gsc_properties')->whereIn('site_id', $siteIds)->pluck('id');

        if ($propertyIds->isEmpty()) {
            return [collect(), null];
        }

        $rows = DB::table('gsc_page_metrics')
            ->whereIn('gsc_property_id', $propertyIds)
            ->selectRaw('date, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(position) as position')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        if ($rows->isEmpty()) {
            return [collect(), null];
        }

        $trend = $rows->map(fn (object $row): array => [
            'date' => $row->date,
            'clicks' => (int) $row->clicks,
            'impressions' => (int) $row->impressions,
            'position' => round((float) $row->position, 1),
        ])->values();

        $recent = $rows->slice(-14);
        $previous = $rows->slice(0, -14);

        return [$trend, $this->buildKpis($recent, $previous)];
    }

    /**
     * @param  Collection<int, object>  $recent
     * @param  Collection<int, object>  $previous
     * @return array<string, array<string, int|float|null>>
     */
    private function buildKpis(Collection $recent, Collection $previous): array
    {
        $clicksRecent = (float) $recent->sum('clicks');
        $clicksPrevious = (float) $previous->sum('clicks');
        $impressionsRecent = (float) $recent->sum('impressions');
        $impressionsPrevious = (float) $previous->sum('impressions');
        $positionRecent = $recent->avg('position');
        $positionPrevious = $previous->avg('position');

        $delta = static fn (float $previousValue, float $current): ?float => $previousValue > 0
            ? round(($current - $previousValue) / $previousValue * 100, 1)
            : null;

        return [
            'clicks' => [
                'value' => (int) $clicksRecent,
                'delta' => $delta($clicksPrevious, $clicksRecent),
            ],
            'impressions' => [
                'value' => (int) $impressionsRecent,
                'delta' => $delta($impressionsPrevious, $impressionsRecent),
            ],
            'position' => [
                'value' => $positionRecent === null ? null : round((float) $positionRecent, 1),
                // کاهش رتبه = بهبود؛ دلتای مثبت یعنی «جایگاه بهتر»
                'delta' => $positionPrevious !== null && $positionRecent !== null
                    ? round((float) $positionPrevious - (float) $positionRecent, 1)
                    : null,
            ],
        ];
    }
}
