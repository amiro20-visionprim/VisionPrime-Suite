<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domains\Seo\Services\ClientGrowthSummary;
use App\Domains\Seo\Services\ClientGrowthTrend;
use App\Domains\Workspace\Contracts\CurrentClient;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientGrowthController extends Controller
{
    public function __invoke(CurrentClient $client, ClientGrowthSummary $summary, ClientGrowthTrend $trendService): Response
    {
        $clientModel = $client->has() ? $client->get() : null;

        $siteIds = $clientModel === null
            ? collect()
            : DB::table('sites')
                ->join('projects', 'projects.id', '=', 'sites.project_id')
                ->where('projects.client_id', $clientModel->getKey())
                ->pluck('sites.id');

        $opportunities = $clientModel === null
            ? collect()
            : DB::table('opportunities')
                ->join('sites', 'sites.id', '=', 'opportunities.site_id')
                ->join('projects', 'projects.id', '=', 'sites.project_id')
                ->where('projects.client_id', $clientModel->getKey())
                ->where('opportunities.status', 'open')
                ->orderByDesc('opportunities.score')
                ->limit(5)
                ->get(['opportunities.id', 'opportunities.type', 'opportunities.score', 'opportunities.explanation', 'sites.name as site_name']);

        [$trend, $kpis] = $clientModel === null || $siteIds->isEmpty()
            ? [collect(), null]
            : $trendService->forSites($siteIds);

        return Inertia::render('Client/Growth', [
            'growthSummary' => $clientModel === null ? null : $summary->for($clientModel),
            'opportunities' => $opportunities,
            'trend' => $trend,
            'kpis' => $kpis,
        ]);
    }
}
