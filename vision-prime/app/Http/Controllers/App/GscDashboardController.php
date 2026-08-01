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
        $accounts = \DB::table('gsc_accounts')->where('organization_id', $org->id())->get(['id', 'email', 'status', 'token_expires_at']);
        $properties = \DB::table('gsc_properties')->join('sites', 'sites.id', '=', 'gsc_properties.site_id')->whereIn('sites.organization_id', [$org->id()])->select('gsc_properties.*', 'sites.name as site_name')->get();
        $runs = \DB::table('gsc_import_runs')->join('gsc_properties', 'gsc_properties.id', '=', 'gsc_import_runs.gsc_property_id')->join('sites', 'sites.id', '=', 'gsc_properties.site_id')->where('sites.organization_id', $org->id())->latest('gsc_import_runs.id')->limit(20)->get(['gsc_import_runs.*', 'sites.name as site_name']);

        return Inertia::render('App/Gsc/Dashboard', ['accounts' => $accounts, 'properties' => $properties, 'runs' => $runs]);
    }
}
