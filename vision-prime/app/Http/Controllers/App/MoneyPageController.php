<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class MoneyPageController extends Controller
{
    public function index(CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $audits = \DB::table('money_page_audits')->join('url_profiles', 'url_profiles.id', '=', 'money_page_audits.url_profile_id')->whereIn('url_profiles.site_id', $siteIds)->latest('money_page_audits.audited_at')->paginate(50, ['money_page_audits.*', 'url_profiles.canonical_url']);

        return Inertia::render('App/MoneyPages/Index', ['audits' => $audits]);
    }
}
