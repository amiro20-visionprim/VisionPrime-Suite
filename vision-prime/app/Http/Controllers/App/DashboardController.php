<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Audit\Actions\ProjectDashboardActivity;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(CurrentOrganization $c, ProjectDashboardActivity $activities): Response
    {
        $id = $c->id();
        $siteIds = Site::query()->where('organization_id', $id)->pluck('id');

        return Inertia::render('App/Dashboard', [
            'counts' => [
                'clients' => Client::query()->where('organization_id', $id)->count(),
                'projects' => Project::query()->where('organization_id', $id)->count(),
                'sites' => $siteIds->count(),
                'connectedSites' => DB::table('site_connections')->whereIn('site_id', $siteIds)->distinct()->count('site_id'),
                'gscConnectedSites' => DB::table('gsc_properties')->whereIn('site_id', $siteIds)->distinct()->count('site_id'),
                'openOpportunities' => DB::table('opportunities')->whereIn('site_id', $siteIds)->where('status', 'open')->count(),
            ],
            'activities' => $activities->forOrganization($id),
        ]);
    }
}
