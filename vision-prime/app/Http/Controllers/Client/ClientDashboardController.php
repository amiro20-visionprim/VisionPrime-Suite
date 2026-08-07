<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domains\Audit\Services\ActivityLabel;
use App\Domains\Seo\Services\ClientGrowthSummary;
use App\Domains\Workspace\Contracts\CurrentClient;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientDashboardController extends Controller
{
    public function __invoke(CurrentClient $client, ClientGrowthSummary $summary): Response
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
                ->limit(3)
                ->get([
                    'opportunities.id',
                    'opportunities.type',
                    'opportunities.score',
                    'opportunities.explanation',
                    'sites.name as site_name',
                ]);

        $latestReport = $clientModel === null
            ? null
            : DB::table('reports')
                ->join('sites', 'sites.id', '=', 'reports.site_id')
                ->join('projects', 'projects.id', '=', 'sites.project_id')
                ->where('projects.client_id', $clientModel->getKey())
                ->where('reports.status', 'published')
                ->latest('reports.published_at')
                ->first([
                    'reports.id',
                    'reports.type',
                    'reports.period_start',
                    'reports.period_end',
                    'reports.content',
                    'reports.published_at',
                ]);

        $recentActivities = $clientModel === null
            ? collect()
            : DB::table('audit_logs')
                ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')
                ->where('audit_logs.organization_id', $clientModel->organization_id)
                ->latest('audit_logs.occurred_at')
                ->limit(5)
                ->get([
                    'audit_logs.action',
                    'audit_logs.occurred_at',
                    'users.name as actor_name',
                ])
                ->map(fn (object $log): array => [
                    'action' => $log->action,
                    'label' => ActivityLabel::for($log->action),
                    'actor_name' => $log->actor_name,
                    'occurred_at' => $log->occurred_at,
                ])
                ->values();

        return Inertia::render('Client/Dashboard', [
            'growthSummary' => $clientModel === null ? null : $summary->for($clientModel),
            'opportunities' => $opportunities,
            'latestReport' => $latestReport,
            'recentActivities' => $recentActivities,
        ]);
    }
}
