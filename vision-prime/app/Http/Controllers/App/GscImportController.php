<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Gsc\Jobs\ImportGscMetrics;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GscImportController extends Controller
{
    public function store(Request $request, CurrentOrganization $org): RedirectResponse
    {
        $data = $request->validate([
            'gsc_property_id' => ['required', 'integer'],
            'date_start' => ['required', 'date', 'before_or_equal:date_end'],
            'date_end' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $property = \DB::table('gsc_properties')
            ->join('sites', 'sites.id', '=', 'gsc_properties.site_id')
            ->where('gsc_properties.id', $data['gsc_property_id'])
            ->where('sites.organization_id', $org->id())
            ->select('gsc_properties.*')
            ->first();

        abort_unless($property, 404, 'ملک سرچ کنسول یافت نشد.');

        $site = Site::findOrFail($property->site_id);
        Gate::authorize('update', $site);

        $runId = \DB::table('gsc_import_runs')->insertGetId([
            'gsc_property_id' => $property->id,
            'date_start' => $data['date_start'],
            'date_end' => $data['date_end'],
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ImportGscMetrics::dispatch($runId);

        return back()->with('status', 'وارد کردن دادهٔ سرچ کنسول در صف قرار گرفت.');
    }
}
