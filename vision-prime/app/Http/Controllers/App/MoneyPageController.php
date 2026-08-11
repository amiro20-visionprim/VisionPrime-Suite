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
        $audits = \DB::table('money_page_audits')
            ->join('url_profiles', 'url_profiles.id', '=', 'money_page_audits.url_profile_id')
            ->join('sites', 'sites.id', '=', 'url_profiles.site_id')
            ->whereIn('url_profiles.site_id', $siteIds)
            ->latest('money_page_audits.audited_at')
            ->paginate(50, [
                'money_page_audits.id',
                'money_page_audits.score',
                'money_page_audits.audited_at',
                'url_profiles.canonical_url',
                'sites.name as site_name',
                \Illuminate\Support\Facades\DB::raw('(SELECT COUNT(*) FROM money_page_issues i WHERE i.money_page_audit_id = money_page_audits.id) as issues_count'),
            ]);

        return Inertia::render('App/MoneyPages/Index', ['audits' => $audits]);
    }

    public function show(int $audit, CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $item = \DB::table('money_page_audits')
            ->join('url_profiles', 'url_profiles.id', '=', 'money_page_audits.url_profile_id')
            ->join('sites', 'sites.id', '=', 'url_profiles.site_id')
            ->whereIn('url_profiles.site_id', $siteIds)
            ->where('money_page_audits.id', $audit)
            ->first(['money_page_audits.*', 'url_profiles.canonical_url', 'url_profiles.id as url_profile_id', 'url_profiles.metadata', 'url_profiles.site_id', 'sites.name as site_name'])
            ?? abort(404);

        $issues = \DB::table('money_page_issues')->where('money_page_audit_id', $item->id)->get(['key', 'severity', 'explanation']);
        $gsc = json_decode($item->metadata ?? '{}', true)['gsc'] ?? null;
        $opportunities = \DB::table('opportunities')
            ->where('url_profile_id', $item->url_profile_id)
            ->latest('id')
            ->get(['id', 'type', 'score', 'explanation']);
        $reviewItemId = \DB::table('review_items')
            ->where('site_id', $item->site_id)
            ->where('subject_type', 'money_page_audit')
            ->where('subject_id', $item->id)
            ->value('id');

        return Inertia::render('App/MoneyPages/Show', [
            'audit' => [
                'id' => $item->id,
                'score' => $item->score,
                'canonicalUrl' => $item->canonical_url,
                'siteName' => $item->site_name,
                'auditedAt' => $item->audited_at,
                'gsc' => $gsc,
                'issues' => $issues->map(fn ($i): array => ['key' => $i->key, 'severity' => $i->severity, 'explanation' => $i->explanation])->values(),
                'opportunities' => $opportunities->map(fn ($o): array => ['id' => $o->id, 'type' => $o->type, 'score' => $o->score, 'explanation' => $o->explanation])->values(),
                'reviewItemId' => $reviewItemId,
            ],
        ]);
    }
}
