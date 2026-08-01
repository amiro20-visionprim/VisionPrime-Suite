<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(CurrentOrganization $c): Response
    {
        $id = $c->id();

        return Inertia::render('App/DashboardPlaceholder', ['counts' => ['clients' => Client::query()->where('organization_id', $id)->count(), 'projects' => Project::query()->where('organization_id', $id)->count(), 'sites' => Site::query()->where('organization_id', $id)->count(), 'connectedSites' => 0, 'openOpportunities' => 0]]);
    }
}
