<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class GscMetricsController extends Controller
{
    public function pages(CurrentOrganization $org): Response
    {
        $rows = \DB::table('gsc_page_metrics')->join('gsc_properties', 'gsc_properties.id', '=', 'gsc_page_metrics.gsc_property_id')->join('sites', 'sites.id', '=', 'gsc_properties.site_id')->where('sites.organization_id', $org->id())->latest('gsc_page_metrics.date')->paginate(50);

        return Inertia::render('App/Gsc/Pages', ['metrics' => $rows]);
    }

    public function queries(CurrentOrganization $org): Response
    {
        $rows = \DB::table('gsc_query_metrics')->join('gsc_properties', 'gsc_properties.id', '=', 'gsc_query_metrics.gsc_property_id')->join('sites', 'sites.id', '=', 'gsc_properties.site_id')->where('sites.organization_id', $org->id())->latest('gsc_query_metrics.date')->paginate(50);

        return Inertia::render('App/Gsc/Queries', ['metrics' => $rows]);
    }
}
