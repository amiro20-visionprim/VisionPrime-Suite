<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ConversionRiskController extends Controller
{
    public function index(CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $risks = \DB::table('conversion_risks')->join('url_profiles', 'url_profiles.id', '=', 'conversion_risks.url_profile_id')->whereIn('url_profiles.site_id', $siteIds)->latest('conversion_risks.score')->paginate(50, ['conversion_risks.*', 'url_profiles.canonical_url']);

        return Inertia::render('App/ConversionRisks/Index', ['risks' => $risks]);
    }
}
