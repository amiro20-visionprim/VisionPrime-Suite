<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Actions;

use Illuminate\Support\Facades\DB;

/**
 * خلاصهٔ «تأثیر محتوا» برای داشبورد: گزارش تأثیر GSC همهٔ کامندهای
 * publish_new_article اجراشدهٔ سازمان را جمع می‌زند و برترین/ضعیف‌ترین
 * بهبودها را برمی‌گرداند (بدون جعل عدد — فقط دادهٔ واقعی GSC).
 *
 * @return array<string, mixed>
 */
class BuildContentImpactSummary
{
    public function handle(array $siteIds, int $windowDays = BuildPublishImpactReport::WINDOW_DAYS): array
    {
        $commands = DB::table('commands')
            ->whereIn('site_id', $siteIds)
            ->where('type', 'publish_new_article')
            ->whereIn('status', ['executed', 'rolled_back'])
            ->whereNotNull('published_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $build = app(BuildPublishImpactReport::class);
        $sites = DB::table('sites')->whereIn('id', $siteIds)->get()->keyBy('id');

        $reports = [];
        $best = null;
        $worst = null;
        $declines = [];

        foreach ($commands as $command) {
            $report = $build->handle($command, $windowDays);
            if (($report['status'] ?? '') !== 'ready') {
                continue;
            }

            $deltaClicks = (int) ($report['delta']['clicks'] ?? 0);
            $deltaPosition = (float) ($report['delta']['position'] ?? 0);
            $site = $sites->get($command->site_id);
            $entry = [
                'command_id' => (int) $command->id,
                'site_name' => $site?->name,
                'url' => (string) ($report['url'] ?? ''),
                'verdict' => (string) ($report['verdict'] ?? 'stable'),
                'delta' => [
                    'position' => $deltaPosition,
                    'clicks' => $deltaClicks,
                    'impressions' => (int) ($report['delta']['impressions'] ?? 0),
                ],
                'before' => $report['before'],
                'after' => $report['after'],
            ];
            $reports[] = $entry;

            if ($best === null || $deltaClicks > $best['delta']['clicks']) {
                $best = $entry;
            }
            if ($worst === null || $deltaClicks < $worst['delta']['clicks']) {
                $worst = $entry;
            }
            if ($entry['verdict'] === 'declined') {
                $declines[] = $entry;
            }
        }

        $verdicts = array_count_values(array_column($reports, 'verdict'));

        return [
            'published' => count($reports),
            'reported' => count($reports),
            'insufficient_data' => max(0, $commands->count() - count($reports)),
            'verdicts' => [
                'improved' => $verdicts['improved'] ?? 0,
                'declined' => $verdicts['declined'] ?? 0,
                'stable' => $verdicts['stable'] ?? 0,
            ],
            'best' => $best,
            'worst' => $worst,
            // مواردی که پس از انتشار افت کرده‌اند — برای هشدار خودکار در داشبورد
            // (مرتب‌شده از بیشترین افت کلیک به کمترین).
            'declines' => collect($declines)
                ->sortByDesc(fn (array $d): int => $d['delta']['clicks'])
                ->values()
                ->all(),
        ];
    }
}
