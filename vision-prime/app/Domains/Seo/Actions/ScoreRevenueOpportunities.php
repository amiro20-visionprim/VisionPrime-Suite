<?php

declare(strict_types=1);

namespace App\Domains\Seo\Actions;

use App\Domains\Workspace\Models\Site;

class ScoreRevenueOpportunities
{
    public function handle(Site $site): int
    {
        $insights = \DB::table('keyword_insights')->where('site_id', $site->id)->get();
        $count = 0;
        foreach ($insights as $i) {
            $m = json_decode($i->latest_metrics, true) ?? [];
            $im = (float) ($m['impressions'] ?? 0);
            $clicks = (float) ($m['clicks'] ?? 0);
            $ctr = (float) ($m['ctr'] ?? 0);
            $position = (float) ($m['position'] ?? 100);
            $potential = max(0, min(1, (20 - $position) / 20));
            $ctrGap = max(0, min(1, .12 - $ctr));
            $score = min(100, ($im / 1000 * 30) + ($potential * 40) + ($ctrGap * 200) + ($clicks > 0 ? 5 : 0));
            $opp = \DB::table('opportunities')->updateOrInsert(['site_id' => $site->id, 'keyword_insight_id' => $i->id, 'type' => 'revenue_opportunity'], ['url_profile_id' => $i->mapped_url_profile_id, 'score' => $score, 'confidence' => .65, 'status' => 'open', 'explanation' => 'Score based on impressions, CTR gap, position and ranking potential.', 'updated_at' => now(), 'created_at' => now()]);
            $op = \DB::table('opportunities')->where('site_id', $site->id)->where('keyword_insight_id', $i->id)->where('type', 'revenue_opportunity')->first();
            foreach (['impressions' => [$im, .30], 'ranking_potential' => [$potential, .40], 'ctr_gap' => [$ctrGap, .20]] as $key => [$raw,$weight]) {
                \DB::table('opportunity_factors')->updateOrInsert(['opportunity_id' => $op->id, 'key' => $key], ['weight' => $weight, 'raw_value' => $raw, 'normalized_value' => $raw, 'explanation' => $key, 'updated_at' => now(), 'created_at' => now()]);
            }$count++;
        }

        return $count;
    }
}
