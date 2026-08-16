<?php

declare(strict_types=1);

namespace App\Domains\Automation\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * تقویم محتوایی — پیشنهاد هوشمند زمان انتشار (روز + ساعت).
 *
 * - بهترین روز هفته: میانگین کلیک هر روز هفته از gsc_page_metrics اخیر
 * - بهترین ساعت: میانگین کلیک هر ساعت از gsc_hourly_metrics اخیر (دادهٔ واقعی GSC)
 * - خروجی = اولین رخداد آیندهٔ بهترین روز، در بهترین ساعت (ساعت ۱۰:۰۰ در نبود دادهٔ ساعتی)
 * بدون دادهٔ کافی → null (پیشنهادی نمی‌دهد؛ D-019: هرگز عدد جعل نمی‌شود).
 */
class SuggestPublishSlot
{
    public const WEEKDAY_LABELS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    public const WINDOW_DAYS = 28;

    public const FALLBACK_HOUR = 10;

    /** @return array{weekday: int, label: string, hour: int, datetime: string, avg_clicks: float, samples: int, source: string}|null */
    public function suggest(int $siteId, ?Carbon $now = null): ?array
    {
        $now ??= now();
        $since = $now->copy()->subDays(self::WINDOW_DAYS);

        $propertyId = DB::table('gsc_properties')->where('site_id', $siteId)->value('id');
        if ($propertyId === null) {
            return null;
        }

        $bestWeekday = $this->bestWeekday((int) $propertyId, $since);
        if ($bestWeekday === null) {
            return null;
        }

        $bestHour = $this->bestHour((int) $propertyId, $since);
        $source = $bestHour !== null ? 'hourly' : 'weekday';
        $hour = $bestHour['hour'] ?? self::FALLBACK_HOUR;

        // اولین رخداد آیندهٔ همان روز هفته (حداقل فردا؛ اگر امروز همان روز باشد → هفتهٔ بعد)
        $daysUntil = ($bestWeekday['weekday'] - ((int) $now->format('w') + 1) % 7 + 7) % 7;
        if ($daysUntil === 0) {
            $daysUntil = 7;
        }
        $datetime = $now->copy()->addDays($daysUntil)->setTime($hour, 0)->setSeconds(0);

        return [
            'weekday' => $bestWeekday['weekday'],
            'label' => self::WEEKDAY_LABELS[$bestWeekday['weekday']],
            'hour' => $hour,
            'datetime' => $datetime->toDateTimeString(),
            'avg_clicks' => $bestHour['avg_clicks'] ?? $bestWeekday['avg_clicks'],
            'samples' => $bestHour['samples'] ?? $bestWeekday['samples'],
            'source' => $source,
        ];
    }

    /** @return array{weekday: int, avg_clicks: float, samples: int}|null */
    private function bestWeekday(int $propertyId, Carbon $since): ?array
    {
        $rows = DB::table('gsc_page_metrics')
            ->where('gsc_property_id', $propertyId)
            ->where('date', '>=', $since->toDateString())
            ->selectRaw('date, SUM(clicks) as clicks')
            ->groupBy('date')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $byWeekday = [];
        foreach ($rows as $row) {
            $weekday = ((int) Carbon::parse($row->date)->dayOfWeek + 1) % 7; // شنبه=0 … جمعه=6
            $byWeekday[$weekday][] = (float) $row->clicks;
        }

        $best = null;
        foreach ($byWeekday as $weekday => $values) {
            $avg = array_sum($values) / count($values);
            if ($best === null || $avg > $best['avg_clicks']) {
                $best = ['weekday' => $weekday, 'avg_clicks' => $avg, 'samples' => count($values)];
            }
        }

        return $best;
    }

    /** @return array{hour: int, avg_clicks: float, samples: int}|null */
    private function bestHour(int $propertyId, Carbon $since): ?array
    {
        $rows = DB::table('gsc_hourly_metrics')
            ->where('gsc_property_id', $propertyId)
            ->where('date', '>=', $since->toDateString())
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $byHour = [];
        foreach ($rows as $row) {
            $byHour[(int) $row->hour][] = (float) $row->clicks;
        }

        $best = null;
        foreach ($byHour as $hour => $values) {
            $avg = array_sum($values) / count($values);
            if ($best === null || $avg > $best['avg_clicks']) {
                $best = ['hour' => $hour, 'avg_clicks' => $avg, 'samples' => count($values)];
            }
        }

        return $best;
    }
}
