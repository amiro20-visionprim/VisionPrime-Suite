<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class GscDashboardController extends Controller
{
    public function __invoke(CurrentOrganization $org): Response
    {
        $orgId = $org->id();

        $accounts = \DB::table('gsc_accounts')->where('organization_id', $orgId)->get(['id', 'email', 'status', 'token_expires_at']);
        $properties = \DB::table('gsc_properties')
            ->join('sites', 'sites.id', '=', 'gsc_properties.site_id')
            ->where('sites.organization_id', $orgId)
            ->select('gsc_properties.*', 'sites.name as site_name')
            ->get();
        $runs = \DB::table('gsc_import_runs')
            ->join('gsc_properties', 'gsc_properties.id', '=', 'gsc_import_runs.gsc_property_id')
            ->join('sites', 'sites.id', '=', 'gsc_properties.site_id')
            ->where('sites.organization_id', $orgId)
            ->latest('gsc_import_runs.id')
            ->limit(20)
            ->get(['gsc_import_runs.*', 'sites.name as site_name']);

        $propertyIds = $properties->pluck('id')->toArray();

        // KPI: last 28 days
        $kpis = $this->buildKpis($propertyIds);

        // Top pages
        $topPages = \DB::table('gsc_page_metrics')
            ->whereIn('gsc_property_id', $propertyIds)
            ->selectRaw('page_url, SUM(clicks) as total_clicks, SUM(impressions) as total_impressions, AVG(ctr) as avg_ctr, AVG(position) as avg_position')
            ->groupBy('page_url')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get();

        // Top queries
        $topQueries = \DB::table('gsc_query_metrics')
            ->whereIn('gsc_property_id', $propertyIds)
            ->selectRaw('query, SUM(clicks) as total_clicks, SUM(impressions) as total_impressions, AVG(ctr) as avg_ctr, AVG(position) as avg_position')
            ->groupBy('query')
            ->orderByDesc('total_clicks')
            ->limit(10)
            ->get();

        // Trend: daily clicks for last 28 days
        $trend = \DB::table('gsc_page_metrics')
            ->whereIn('gsc_property_id', $propertyIds)
            ->where('date', '>=', now()->subDays(28)->toDateString())
            ->selectRaw('date, SUM(clicks) as clicks, SUM(impressions) as impressions')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return Inertia::render('App/Gsc/Dashboard', [
            'accounts' => $accounts,
            'properties' => $properties,
            'runs' => $runs,
            'kpis' => $kpis,
            'topPages' => $topPages->map(fn ($p) => [
                'url' => $p->page_url,
                'clicks' => (int) $p->total_clicks,
                'impressions' => (int) $p->total_impressions,
                'ctr' => round((float) $p->avg_ctr * 100, 1),
                'position' => round((float) $p->avg_position, 1),
            ]),
            'topQueries' => $topQueries->map(fn ($q) => [
                'query' => $q->query,
                'clicks' => (int) $q->total_clicks,
                'impressions' => (int) $q->total_impressions,
                'ctr' => round((float) $q->avg_ctr * 100, 1),
                'position' => round((float) $q->avg_position, 1),
            ]),
            'trend' => $trend,
        ]);
    }

    private function buildKpis(array $propertyIds): ?array
    {
        if ($propertyIds === []) {
            return null;
        }

        $now = \DB::table('gsc_page_metrics')
            ->whereIn('gsc_property_id', $propertyIds)
            ->selectRaw('SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(ctr) as ctr, AVG(position) as position')
            ->first();

        if (! $now || ($now->clicks ?? 0) === 0) {
            return null;
        }

        return [
            'totalClicks' => (int) $now->clicks,
            'totalImpressions' => (int) $now->impressions,
            'avgCtr' => round((float) $now->ctr * 100, 1),
            'avgPosition' => round((float) $now->position, 1),
            'clicksDelta' => null,
            'impressionsDelta' => null,
        ];
    }
}
