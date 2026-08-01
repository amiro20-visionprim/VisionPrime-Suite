<?php

declare(strict_types=1);

namespace App\Domains\Seo\Actions;

use App\Domains\Workspace\Models\Site;

class DetectCannibalization
{
    public function handle(Site $site): int
    {
        $propertyIds = \DB::table('gsc_properties')->where('site_id', $site->id)->pluck('id');
        $rows = \DB::table('gsc_query_page_metrics')->whereIn('gsc_property_id', $propertyIds)->select('query', \DB::raw('COUNT(DISTINCT page_url) as pages'), \DB::raw('SUM(impressions) as impressions'))->groupBy('query')->having('pages', '>', 1)->having('impressions', '>=', 20)->get();
        $count = 0;
        foreach ($rows as $row) {
            $insight = \DB::table('keyword_insights')->where('site_id', $site->id)->where('query_normalized', mb_strtolower($row->query))->first();
            \DB::table('opportunities')->updateOrInsert(['site_id' => $site->id, 'keyword_insight_id' => $insight?->id, 'type' => 'cannibalization'], ['score' => min(100, (float) $row->impressions / 10), 'confidence' => .75, 'status' => 'open', 'explanation' => "{$row->pages} URLs compete for query: {$row->query}", 'updated_at' => now(), 'created_at' => now()]);
            $count++;
        }

        return $count;
    }
}
