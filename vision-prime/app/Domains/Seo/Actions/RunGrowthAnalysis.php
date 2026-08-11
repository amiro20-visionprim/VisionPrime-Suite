<?php

declare(strict_types=1);

namespace App\Domains\Seo\Actions;

use App\Domains\Ai\Actions\CreateReviewItem;
use App\Domains\Workspace\Models\Site;

/**
 * Orchestrates the full growth-intelligence pipeline for one site:
 *
 *  1. Create url_profiles from real GSC page metrics
 *  2. Map keywords to URLs (keyword_insights + intent)
 *  3. Detect keyword cannibalization
 *  4. Score revenue opportunities from keyword insights
 *  5. Audit money pages
 *  6. Detect signal opportunities (ctr gap / keyword / conversion boost)
 *  7. Detect conversion risks
 *  8. Turn risks into recommendations
 *  9. Open review items for the most important flagged money pages
 *
 * Every step is idempotent (updateOrInsert), so it can be re-run safely
 * after every fresh GSC import.
 */
class RunGrowthAnalysis
{
    public function __construct(
        private readonly CreateUrlProfilesFromGsc $createProfiles,
        private readonly MapKeywordsToUrls $mapKeywords,
        private readonly DetectCannibalization $cannibalization,
        private readonly ScoreRevenueOpportunities $revenue,
        private readonly DetectSignalOpportunities $signals,
        private readonly AuditMoneyPages $auditMoneyPages,
        private readonly DetectConversionRisks $conversionRisks,
        private readonly CreateRiskRecommendations $riskRecommendations,
        private readonly CreateReviewItem $createReviewItem,
    ) {}

    /**
     * @return array<string, int> counts per stage
     */
    public function handle(Site $site): array
    {
        return [
            'url_profiles' => $this->createProfiles->handle($site),
            'keyword_insights' => $this->mapKeywords->handle($site),
            'cannibalization' => $this->cannibalization->handle($site),
            'revenue_opportunities' => $this->revenue->handle($site),
            'money_page_audits' => $this->auditMoneyPages->handle($site),
            'signal_opportunities' => $this->signals->handle($site),
            'conversion_risks' => $this->conversionRisks->handle($site),
            'recommendations' => $this->riskRecommendations->handle($site),
            'review_items' => $this->openFlaggedAuditReviews($site),
        ];
    }

    /**
     * Opens a review item for the most important flagged money pages
     * (issues found on real GSC data). Idempotent per subject.
     */
    private function openFlaggedAuditReviews(Site $site): int
    {
        $audits = \DB::table('money_page_audits')
            ->join('url_profiles', 'url_profiles.id', '=', 'money_page_audits.url_profile_id')
            ->where('url_profiles.site_id', $site->id)
            ->where('money_page_audits.score', '<', 100)
            ->orderBy('money_page_audits.score')
            ->limit(10)
            ->get(['money_page_audits.id']);

        $count = 0;
        foreach ($audits as $audit) {
            $this->createReviewItem->handle($site, 'money_page_audit', (int) $audit->id);
            $count++;
        }

        return $count;
    }
}
