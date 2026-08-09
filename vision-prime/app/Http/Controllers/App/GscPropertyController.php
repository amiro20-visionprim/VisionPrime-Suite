<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Gsc\Services\SearchConsoleClient;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GscPropertyController extends Controller
{
    public function index(CurrentOrganization $org, SearchConsoleClient $client): Response
    {
        $accounts = \DB::table('gsc_accounts')->where('organization_id', $org->id())->get();
        $properties = [];
        $googleErrors = [];
        foreach ($accounts as $a) {
            try {
                $properties[$a->id] = $client->properties($a);
            } catch (\Illuminate\Http\Client\RequestException $e) {
                $properties[$a->id] = [];
                $status = $e->response->status();
                $googleErrors[$a->id] = $status === 403
                    ? 'گوگل دسترسی را رد کرد (403). مطمئن شوید «Search Console API» در پروژهٔ Google Cloud فعال است و این حساب به ملک‌های سرچ کنسول دسترسی دارد.'
                    : "دریافت ملک‌ها از گوگل ناموفق بود (خطای {$status}). چند لحظه بعد دوباره امتحان کنید.";
            }
        }
        $sites = Site::query()->where('organization_id', $org->id())->get(['id', 'name', 'canonical_url']);

        return Inertia::render('App/Gsc/Properties', ['accounts' => $accounts, 'properties' => $properties, 'sites' => $sites, 'googleErrors' => $googleErrors]);
    }

    public function store(Request $request, RecordAuditLog $audit): RedirectResponse
    {
        $data = $request->validate(['site_id' => ['required', 'exists:sites,id'], 'gsc_account_id' => ['required', 'exists:gsc_accounts,id'], 'property_uri' => ['required', 'string'], 'property_type' => ['required', 'string']]);
        $site = Site::findOrFail($data['site_id']);
        abort_unless($site->organization_id === app(CurrentOrganization::class)->id(), 404);
        \DB::table('gsc_properties')->updateOrInsert(['site_id' => $site->id, 'property_uri' => $data['property_uri']], ['gsc_account_id' => $data['gsc_account_id'], 'property_type' => $data['property_type'], 'status' => 'selected', 'selected_at' => now(), 'updated_at' => now(), 'created_at' => now()]);
        $audit->handle(action: 'gsc.property_selected', subject: $site, after: ['property_uri' => $data['property_uri']]);

        return back()->with('status', 'Property سرچ کنسول انتخاب شد.');
    }
}
