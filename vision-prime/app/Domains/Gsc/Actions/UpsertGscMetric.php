<?php

declare(strict_types=1);

namespace App\Domains\Gsc\Actions;

class UpsertGscMetric
{
    public function page(int $propertyId, string $date, array $row): void
    {
        \DB::table('gsc_page_metrics')->updateOrInsert(['gsc_property_id' => $propertyId, 'date' => $date, 'page_url' => $row['keys'][0]], ['clicks' => $row['clicks'] ?? 0, 'impressions' => $row['impressions'] ?? 0, 'ctr' => $row['ctr'] ?? 0, 'position' => $row['position'] ?? null]);
    }

    public function query(int $propertyId, string $date, array $row): void
    {
        \DB::table('gsc_query_metrics')->updateOrInsert(['gsc_property_id' => $propertyId, 'date' => $date, 'query' => $row['keys'][0]], ['clicks' => $row['clicks'] ?? 0, 'impressions' => $row['impressions'] ?? 0, 'ctr' => $row['ctr'] ?? 0, 'position' => $row['position'] ?? null]);
    }

    public function queryPage(int $propertyId, string $date, array $row): void
    {
        \DB::table('gsc_query_page_metrics')->updateOrInsert(['gsc_property_id' => $propertyId, 'date' => $date, 'query' => $row['keys'][0], 'page_url' => $row['keys'][1]], ['clicks' => $row['clicks'] ?? 0, 'impressions' => $row['impressions'] ?? 0, 'ctr' => $row['ctr'] ?? 0, 'position' => $row['position'] ?? null]);
    }

    /** متریک ساعتی (date × hour) در سطح property — کلیدها: [date, hour]. */
    public function hour(int $propertyId, string $date, array $row): void
    {
        $date = (string) ($row['keys'][0] ?? $date);
        $hour = (int) ($row['keys'][1] ?? 0);

        \DB::table('gsc_hourly_metrics')->updateOrInsert(
            ['gsc_property_id' => $propertyId, 'date' => $date, 'hour' => $hour],
            ['clicks' => $row['clicks'] ?? 0, 'impressions' => $row['impressions'] ?? 0, 'ctr' => $row['ctr'] ?? 0, 'position' => $row['position'] ?? null],
        );
    }
}
