<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domains\Workspace\Contracts\CurrentClient;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientSiteHealthController extends Controller
{
    public function __invoke(CurrentClient $client): Response
    {
        if (! $client->has()) {
            return Inertia::render('Client/SiteHealth', ['summary' => null, 'sites' => []]);
        }

        $clientModel = $client->get();

        $siteRows = DB::table('sites')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->where('projects.client_id', $clientModel->getKey())
            ->whereNull('sites.deleted_at')
            ->get(['sites.id', 'sites.name', 'sites.canonical_url']);

        $sites = [];
        $connectedCount = 0;
        $attentionCount = 0;

        foreach ($siteRows as $site) {
            $connection = DB::table('site_connections')->where('site_id', $site->id)->first();

            $profileIds = DB::table('url_profiles')->where('site_id', $site->id)->pluck('id');

            $latestAudit = $profileIds->isEmpty()
                ? null
                : DB::table('money_page_audits')
                    ->whereIn('url_profile_id', $profileIds)
                    ->latest('audited_at')
                    ->first();

            $highRisks = $profileIds->isEmpty()
                ? 0
                : DB::table('conversion_risks')->whereIn('url_profile_id', $profileIds)->where('severity', 'high')->count();

            $issueCount = $latestAudit === null
                ? 0
                : DB::table('money_page_issues')->where('money_page_audit_id', $latestAudit->id)->count();

            $connected = $connection !== null && $connection->status === 'connected';
            $needsAttention = ($latestAudit !== null && (float) $latestAudit->score < 70) || $highRisks > 0;

            $connectedCount += $connected ? 1 : 0;
            $attentionCount += $needsAttention ? 1 : 0;

            $sites[] = [
                'id' => $site->id,
                'name' => $site->name,
                'canonical_url' => $site->canonical_url,
                'connected' => $connected,
                'last_seen_at' => $connection?->last_seen_at,
                'audit_score' => $latestAudit !== null ? round((float) $latestAudit->score, 1) : null,
                'issue_count' => $issueCount,
                'high_risk_count' => $highRisks,
                'url_count' => $profileIds->count(),
            ];
        }

        return Inertia::render('Client/SiteHealth', [
            'summary' => [
                'total_sites' => count($sites),
                'connected_sites' => $connectedCount,
                'needs_attention' => $attentionCount,
            ],
            'sites' => $sites,
        ]);
    }
}
