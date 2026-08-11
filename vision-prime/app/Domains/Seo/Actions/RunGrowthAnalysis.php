<?php

declare(strict_types=1);

namespace App\Domains\Seo\Actions;

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
        ];
    }
}
