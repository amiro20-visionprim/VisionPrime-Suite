<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Actions;

use App\Domains\Workspace\Models\Site;

class BuildExecutiveReport
{
    public function handle(Site $site, string $type, string $start, string $end, ?int $userId = null): int
    {
        $impactEvents = \DB::table('impact_events')
            ->where('site_id', $site->id)
            ->latest('observed_at')
            ->limit(5)
            ->get(['source_type', 'attribution_note', 'observed_at']);

        $summary = [
            'site' => $site->name,
            'period' => ['start' => $start, 'end' => $end],
            'opportunities' => \DB::table('opportunities')->where('site_id', $site->id)->where('status', 'open')->count(),
            'money_pages' => \DB::table('money_page_audits')->join('url_profiles', 'url_profiles.id', '=', 'money_page_audits.url_profile_id')->where('url_profiles.site_id', $site->id)->count(),
            'high_risks' => \DB::table('conversion_risks')->join('url_profiles', 'url_profiles.id', '=', 'conversion_risks.url_profile_id')->where('url_profiles.site_id', $site->id)->where('conversion_risks.severity', 'high')->count(),
            'recommendations' => \DB::table('recommendations')->where('site_id', $site->id)->whereIn('status', ['draft', 'active'])->count(),
            'impact_events' => $impactEvents->count(),
            'recent_impacts' => $impactEvents->map(fn (object $event): array => [
                'source_type' => $event->source_type,
                'note' => $event->attribution_note,
                'observed_at' => $event->observed_at,
            ])->values(),
        ];

        return \DB::table('reports')->insertGetId(['site_id' => $site->id, 'type' => $type, 'period_start' => $start, 'period_end' => $end, 'status' => 'draft', 'content' => json_encode($summary), 'generated_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
    }
}
