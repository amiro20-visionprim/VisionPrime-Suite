<?php

declare(strict_types=1);

namespace App\Domains\Seo\Actions;

use App\Domains\Workspace\Models\Site;

/**
 * Derives growth opportunities directly from real GSC signals
 * (keyword insights and money page audits):
 *
 *  - ctr_gap:          keyword has meaningful impressions but a weak click rate
 *  - keyword_opportunity: keyword ranks on page 1-2 and deserves a targeted piece
 *  - conversion_boost: money page underperforms on search signals
 */
class DetectSignalOpportunities
{
    public function handle(Site $site): int
    {
        $count = 0;
        $count += $this->ctrGapAndKeywordOpportunities($site);
        $count += $this->conversionBoosts($site);

        return $count;
    }

    private function ctrGapAndKeywordOpportunities(Site $site): int
    {
        $insights = \DB::table('keyword_insights')->where('site_id', $site->id)->get();
        $count = 0;

        foreach ($insights as $insight) {
            $metrics = json_decode($insight->latest_metrics, true) ?? [];
            $impressions = (float) ($metrics['impressions'] ?? 0);
            $clicks = (float) ($metrics['clicks'] ?? 0);
            $ctr = (float) ($metrics['ctr'] ?? 0);
            $position = (float) ($metrics['position'] ?? 100);

            $query = $insight->query_normalized;

            if ($impressions >= 20 && $ctr < 0.03 && $position <= 50) {
                \DB::table('opportunities')->updateOrInsert(
                    ['site_id' => $site->id, 'keyword_insight_id' => $insight->id, 'type' => 'ctr_gap'],
                    [
                        'url_profile_id' => $insight->mapped_url_profile_id,
                        'score' => min(100, round($impressions / 10 + (0.03 - $ctr) * 1500)),
                        'confidence' => 0.7,
                        'status' => 'open',
                        'explanation' => sprintf('عبارت «%s» نمایش خوبی دارد (%d نمایش) اما نرخ کلیک آن پایین است؛ بهبود عنوان متا می‌تواند کلیک‌ها را افزایش دهد.', $query, (int) $impressions),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $count++;
            }

            if ($impressions >= 50 && $position >= 5 && $position <= 15 && $ctr >= 0.03) {
                \DB::table('opportunities')->updateOrInsert(
                    ['site_id' => $site->id, 'keyword_insight_id' => $insight->id, 'type' => 'keyword_opportunity'],
                    [
                        'url_profile_id' => $insight->mapped_url_profile_id,
                        'score' => min(100, round(50 + (15 - $position) * 4)),
                        'confidence' => 0.75,
                        'status' => 'open',
                        'explanation' => sprintf('عبارت «%s» در صفحهٔ اول نتایج است (جایگاه %s)؛ تولید محتوای هدفمند می‌تواند رتبه را به نیمهٔ بالای صفحهٔ اول برساند.', $query, number_format($position, 1)),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    private function conversionBoosts(Site $site): int
    {
        $audits = \DB::table('money_page_audits')
            ->join('url_profiles', 'url_profiles.id', '=', 'money_page_audits.url_profile_id')
            ->where('url_profiles.site_id', $site->id)
            ->where('money_page_audits.score', '<', 70)
            ->get(['money_page_audits.id', 'money_page_audits.url_profile_id', 'money_page_audits.score', 'url_profiles.canonical_url']);

        $count = 0;
        foreach ($audits as $audit) {
            \DB::table('opportunities')->updateOrInsert(
                ['site_id' => $site->id, 'url_profile_id' => $audit->url_profile_id, 'type' => 'conversion_boost'],
                [
                    'keyword_insight_id' => null,
                    'score' => (int) $audit->score,
                    'confidence' => 0.7,
                    'status' => 'open',
                    'explanation' => sprintf('صفحهٔ «%s» در سیگنال‌های جستجو عملکرد ضعیفی دارد (امتیاز %d)؛ بهبود محتوا و CTA می‌تواند تبدیل بیشتری ایجاد کند.', $audit->canonical_url, (int) $audit->score),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }
}
