<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversionRiskController extends Controller
{
    public function index(Request $request, CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');

        $severity = $request->query('severity');
        if ($severity !== null && ! in_array($severity, ['low', 'medium', 'high'], true)) {
            $severity = null;
        }

        $query = \DB::table('conversion_risks')
            ->join('url_profiles', 'url_profiles.id', '=', 'conversion_risks.url_profile_id')
            ->whereIn('url_profiles.site_id', $siteIds);

        if ($severity !== null) {
            $query->where('conversion_risks.severity', $severity);
        }

        $risks = $query->latest('conversion_risks.score')->paginate(50, [
            'conversion_risks.*',
            'url_profiles.canonical_url',
            'url_profiles.id as url_profile_id',
            \Illuminate\Support\Facades\DB::raw('(SELECT money_page_audits.id FROM money_page_audits WHERE money_page_audits.url_profile_id = url_profiles.id ORDER BY money_page_audits.id DESC LIMIT 1) as audit_id'),
        ])->withQueryString();

        return Inertia::render('App/ConversionRisks/Index', [
            'risks' => $risks,
            'filters' => ['severity' => $severity],
        ]);
    }
}
