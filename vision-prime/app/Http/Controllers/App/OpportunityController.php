<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    public function index(CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $items = \DB::table('opportunities')->leftJoin('keyword_insights', 'keyword_insights.id', '=', 'opportunities.keyword_insight_id')->leftJoin('url_profiles', 'url_profiles.id', '=', 'opportunities.url_profile_id')->whereIn('opportunities.site_id', $siteIds)->orderByDesc('opportunities.score')->paginate(50, ['opportunities.*', 'keyword_insights.query_normalized', 'url_profiles.canonical_url']);

        return Inertia::render('App/Opportunities/Index', ['opportunities' => $items]);
    }

    public function show(int $opportunity, CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $item = \DB::table('opportunities')->whereIn('site_id', $siteIds)->where('id', $opportunity)->firstOrFail();
        $factors = \DB::table('opportunity_factors')->where('opportunity_id', $item->id)->get();

        return Inertia::render('App/Opportunities/Show', ['opportunity' => $item, 'factors' => $factors]);
    }
}
