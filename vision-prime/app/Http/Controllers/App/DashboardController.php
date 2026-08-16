<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Audit\Actions\ProjectDashboardActivity;
use App\Domains\Automation\Services\SuggestPublishSlot;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Reporting\Actions\BuildContentImpactSummary;
use App\Domains\Seo\Services\ClientGrowthTrend;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(CurrentOrganization $c, ProjectDashboardActivity $activities, ClientGrowthTrend $trendService): Response
    {
        $id = $c->id();
        $siteIds = Site::query()->where('organization_id', $id)->pluck('id');

        [$trend, $kpis] = $siteIds->isEmpty()
            ? [collect(), null]
            : $trendService->forSites($siteIds);

        return Inertia::render('App/Dashboard', [
            'counts' => [
                'clients' => Client::query()->where('organization_id', $id)->count(),
                'projects' => Project::query()->where('organization_id', $id)->count(),
                'sites' => $siteIds->count(),
                'connectedSites' => DB::table('site_connections')->whereIn('site_id', $siteIds)->distinct()->count('site_id'),
                'gscConnectedSites' => DB::table('gsc_properties')->whereIn('site_id', $siteIds)->distinct()->count('site_id'),
                'openOpportunities' => DB::table('opportunities')->whereIn('site_id', $siteIds)->where('status', 'open')->count(),
                'pendingCommands' => DB::table('commands')->whereIn('site_id', $siteIds)->where('status', 'pending_approval')->count(),
                'pendingReviews' => DB::table('review_items')->whereIn('site_id', $siteIds)->where('status', 'pending_review')->count(),
                'scheduledPublishes' => DB::table('commands')->whereIn('site_id', $siteIds)->where('status', 'scheduled')->where('type', 'publish_new_article')->count(),
            ],
            'contentImpact' => app(BuildContentImpactSummary::class)->handle($siteIds->all()),
            // تقویم محتوایی — بهترین روز/ساعت انتشار برای هر سایت (از GSC واقعی)
            'publishSuggestions' => $this->publishSuggestions($id, $siteIds->all()),
            'trend' => $trend,
            'kpis' => $kpis,
            'approvalQueue' => $siteIds->isEmpty() ? collect() : $this->approvalQueue($siteIds->all()),
            'activities' => $activities->forOrganization($id),
        ]);
    }

    /**
     * صف تأیید سازمان: تغییرات اجرایی در انتظار + بازبینی‌های در انتظار —
     * برای «بررسی و تأییدها» در داشبورد و نقش بازبین.
     *
     * @param  array<int, int>  $siteIds
     * @return Collection<int, array{type: string, id: int, label: string, site_name: string, created_at: string|null}>
     */
    private function approvalQueue(array $siteIds): Collection
    {
        $commands = DB::table('commands')
            ->join('sites', 'sites.id', '=', 'commands.site_id')
            ->whereIn('commands.site_id', $siteIds)
            ->where('commands.status', 'pending_approval')
            ->orderByDesc('commands.created_at')
            ->limit(6)
            ->get([
                'commands.id',
                'commands.type',
                'commands.created_at',
                'sites.name as site_name',
            ])
            ->map(fn (object $row): array => [
                'type' => 'command',
                'id' => (int) $row->id,
                'label' => (string) $row->type,
                'site_name' => (string) $row->site_name,
                'created_at' => $row->created_at,
            ]);

        $reviews = DB::table('review_items')
            ->join('sites', 'sites.id', '=', 'review_items.site_id')
            ->whereIn('review_items.site_id', $siteIds)
            ->where('review_items.status', 'pending_review')
            ->orderByDesc('review_items.created_at')
            ->limit(6)
            ->get([
                'review_items.id',
                'review_items.subject_type',
                'review_items.created_at',
                'sites.name as site_name',
            ])
            ->map(fn (object $row): array => [
                'type' => 'review',
                'id' => (int) $row->id,
                'label' => (string) $row->subject_type,
                'site_name' => (string) $row->site_name,
                'created_at' => $row->created_at,
            ]);

        return $commands->concat($reviews)->sortByDesc('created_at')->values()->take(8);
    }

    /** @param  array<int, int>  $siteIds
     * @return array<int, array{site_id: int, site_name: string, label: string, hour: int, datetime: string, source: string}>
     */
    private function publishSuggestions(int $organizationId, array $siteIds): array
    {
        $suggest = app(SuggestPublishSlot::class);
        $sites = DB::table('sites')->whereIn('id', $siteIds)->get(['id', 'name']);

        $result = [];
        foreach ($sites as $site) {
            $slot = $suggest->suggest((int) $site->id);
            if ($slot === null) {
                continue;
            }
            $result[] = [
                'site_id' => (int) $site->id,
                'site_name' => (string) $site->name,
                'label' => $slot['label'],
                'hour' => $slot['hour'],
                'datetime' => $slot['datetime'],
                'source' => $slot['source'],
            ];
        }

        return $result;
    }
}
