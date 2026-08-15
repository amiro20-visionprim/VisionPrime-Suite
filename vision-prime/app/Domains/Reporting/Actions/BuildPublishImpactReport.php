<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Actions;

use Illuminate\Support\Carbon;

/**
 * گزارش تأثیر پس از انتشار برای کامندهای publish_new_article.
 *
 * متریک‌های GSC صفحهٔ هدف (کلیک، نمایش، میانگین جایگاه) را در دو پنجره مقایسه می‌کند:
 *  - baseline: پنجرهٔ N روز پیش از انتشار
 *  - observed: پنجرهٔ N روز پس از انتشار (تا امروز اگر هنوز کامل نشده باشد)
 * و دلتا + وضعیت (بهبود/افت/بدون تغییر) را برمی‌گرداند.
 *
 * اگر دادهٔ GSC کافی نباشد (صفحه هنوز ایندکس نشده یا import نشده)، وضعیت
 * insufficient_data با دلیل دقیق برمی‌گردد — بدون جعل عدد.
 *
 * @return array<string, mixed>
 */
class BuildPublishImpactReport
{
    /** پنجرهٔ پیش‌فرض مقایسه (روز). */
    public const WINDOW_DAYS = 14;

    public function handle(object $command, int $windowDays = self::WINDOW_DAYS): array
    {
        if ($command->type !== 'publish_new_article') {
            return ['status' => 'not_applicable', 'reason' => 'command_type'];
        }
        if (! in_array($command->status, ['executed', 'rolled_back'], true)) {
            return ['status' => 'not_published', 'reason' => 'command_not_executed'];
        }
        if ($command->published_at === null) {
            return ['status' => 'not_published', 'reason' => 'no_published_at'];
        }

        $payload = json_decode((string) ($command->payload ?? '{}'), true) ?? [];
        $targetUrl = $this->targetUrl($command, $payload);
        if ($targetUrl === '') {
            return ['status' => 'insufficient_data', 'reason' => 'no_target_url'];
        }

        $property = \DB::table('gsc_properties')
            ->where('site_id', $command->site_id)
            ->where('status', 'selected')
            ->latest('id')
            ->first();
        if ($property === null) {
            return ['status' => 'insufficient_data', 'reason' => 'no_gsc_property'];
        }

        $published = Carbon::parse($command->published_at)->startOfDay();
        $before = $this->aggregate($property->id, $targetUrl, $published->copy()->subDays($windowDays), $published->copy()->subDay());
        $after = $this->aggregate($property->id, $targetUrl, $published->copy()->addDay(), $published->copy()->addDays($windowDays));

        if ($before['days'] === 0 || $after['days'] === 0) {
            return [
                'status' => 'insufficient_data',
                'reason' => $before['days'] === 0 && $after['days'] === 0
                    ? 'no_gsc_metrics'
                    : ($before['days'] === 0 ? 'no_baseline' : 'no_observed'),
                'url' => $targetUrl,
                'window_days' => $windowDays,
                'published_at' => $command->published_at,
            ];
        }

        $deltaPosition = $this->rounded($after['avg_position'] - $before['avg_position']);
        $deltaClicks = $after['clicks'] - $before['clicks'];
        $deltaImpressions = $after['impressions'] - $before['impressions'];

        $verdict = match (true) {
            $deltaPosition < -0.5 && $deltaClicks >= 0 => 'improved',
            $deltaPosition > 0.5 || $deltaClicks < 0 => 'declined',
            default => 'stable',
        };

        return [
            'status' => 'ready',
            'url' => $targetUrl,
            'window_days' => $windowDays,
            'published_at' => $command->published_at,
            'before' => $before,
            'after' => $after,
            'series' => $this->series($property->id, $targetUrl, $published, $windowDays),
            'delta' => [
                'position' => $deltaPosition,
                'clicks' => $deltaClicks,
                'impressions' => $deltaImpressions,
            ],
            'verdict' => $verdict,
        ];
    }

    /**
     * سری روزانهٔ جایگاه/کلیک برای نمودار روند (قبل/بعد از انتشار).
     * هر روز با ساختار { date, position|null, clicks } — روزهای بدون داده حذف نمی‌شوند
     * تا محور زمانی تراز بماند؛ مقدار null = داده‌ای در آن روز ثبت نشده.
     *
     * @return array<int, array{date: string, position: float|null, clicks: int}>
     */
    private function series(int $propertyId, string $url, Carbon $published, int $windowDays): array
    {
        $start = $published->copy()->subDays($windowDays);
        $end = $published->copy()->addDays($windowDays);

        $rows = \DB::table('gsc_page_metrics')
            ->where('gsc_property_id', $propertyId)
            ->where('page_url', $url)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date', 'clicks', 'position'])
            ->keyBy('date');

        $points = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();
            $row = $rows->get($key);
            $points[] = [
                'date' => $key,
                'position' => $row !== null && $row->position !== null ? (float) $row->position : null,
                'clicks' => (int) ($row->clicks ?? 0),
            ];
        }

        return $points;
    }

    /**
     * URL کنونیکال از اسکیمای پیشنویس (payload.schema[].url).
     *
     * @param  array<string, mixed>  $payload
     */
    private function schemaCanonicalUrl(array $payload): string
    {
        $schema = $payload['schema'] ?? [];
        if (! is_array($schema)) {
            return '';
        }
        foreach ($schema as $block) {
            if (is_array($block) && ! empty($block['url']) && is_string($block['url'])) {
                return trim($block['url']);
            }
        }

        return '';
    }

    /**
     * URL هدف از payload: url صریح ← کنونیکال اسکیما ← slug + platform_url ← اسلاگ.
     *
     * @param  array<string, mixed>  $payload
     */
    private function targetUrl(object $command, array $payload): string
    {
        if (! empty($payload['url']) && is_string($payload['url'])) {
            return trim((string) $payload['url']);
        }

        // URL کنونیکال داخل اسکیمای پیشنویس (درست برای محصولات: /product/slug/ و مقالات).
        $schemaUrl = $this->schemaCanonicalUrl($payload);
        if ($schemaUrl !== '') {
            return $schemaUrl;
        }

        $slug = trim((string) ($payload['slug'] ?? ''));
        if ($slug === '') {
            return '';
        }

        $connection = \DB::table('site_connections')->where('site_id', $command->site_id)->first();
        $platformUrl = rtrim((string) ($connection->platform_url ?? ''), '/');

        return $platformUrl !== '' ? $platformUrl.'/'.$slug.'/' : '';
    }

    /**
     * @return array{days: int, clicks: int, impressions: int, avg_position: float|null}
     */
    private function aggregate(int $propertyId, string $url, Carbon $start, Carbon $end): array
    {
        $rows = \DB::table('gsc_page_metrics')
            ->where('gsc_property_id', $propertyId)
            ->where('page_url', $url)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get(['date', 'clicks', 'impressions', 'position']);

        $days = $rows->count();
        $clicks = (int) $rows->sum('clicks');
        $impressions = (int) $rows->sum('impressions');
        $positions = $rows->filter(fn (object $r): bool => $r->position !== null)->pluck('position');
        $avgPosition = $positions->count() > 0 ? $this->rounded($positions->avg()) : null;

        return [
            'days' => $days,
            'clicks' => $clicks,
            'impressions' => $impressions,
            'avg_position' => $avgPosition,
        ];
    }

    private function rounded(float $value): float
    {
        return round($value, 2);
    }
}
