<?php

declare(strict_types=1);

namespace App\Domains\Seo\Actions;

use App\Domains\Seo\Services\RuleBasedIntentClassifier;
use App\Domains\Workspace\Models\Site;

class MapKeywordsToUrls
{
    public function __construct(private readonly RuleBasedIntentClassifier $classifier) {}

    public function handle(Site $site): int
    {
        $propertyIds = \DB::table('gsc_properties')->where('site_id', $site->id)->pluck('id');
        $rows = \DB::table('gsc_query_page_metrics')->whereIn('gsc_property_id', $propertyIds)->get();
        $count = 0;
        foreach ($rows as $row) {
            $query = mb_strtolower(trim($row->query));
            $profile = \DB::table('url_profiles')->where('site_id', $site->id)->where('canonical_url', $row->page_url)->first();
            $id = \DB::table('keyword_insights')->updateOrInsert(['site_id' => $site->id, 'query_normalized' => $query], ['mapped_url_profile_id' => $profile?->id, 'latest_metrics' => json_encode(['clicks' => $row->clicks, 'impressions' => $row->impressions, 'ctr' => $row->ctr, 'position' => $row->position]), 'status' => 'active', 'updated_at' => now(), 'created_at' => now()]);
            $insight = \DB::table('keyword_insights')->where('site_id', $site->id)->where('query_normalized', $query)->first();
            $intent = $this->classifier->classify($query);
            \DB::table('intent_classifications')->updateOrInsert(['keyword_insight_id' => $insight->id], ['intent' => $intent['intent'], 'confidence' => $intent['confidence'], 'method' => $intent['method'], 'explanation' => $intent['explanation'], 'rules_version' => $intent['rules_version'], 'updated_at' => now(), 'created_at' => now()]);
            $count++;
        }

        return $count;
    }
}
