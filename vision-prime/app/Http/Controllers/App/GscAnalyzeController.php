<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Seo\Jobs\RunGrowthAnalysisJob;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GscAnalyzeController extends Controller
{
    public function store(Request $request, CurrentOrganization $org): RedirectResponse
    {
        $data = $request->validate([
            'gsc_property_id' => ['required', 'integer'],
        ]);

        $property = \DB::table('gsc_properties')
            ->join('sites', 'sites.id', '=', 'gsc_properties.site_id')
            ->where('gsc_properties.id', $data['gsc_property_id'])
            ->where('sites.organization_id', $org->id())
            ->select('gsc_properties.*')
            ->first();

        abort_unless($property, 404, 'ملک سرچ کنسول یافت نشد.');

        RunGrowthAnalysisJob::dispatch((int) $property->site_id);

        return back()->with('status', 'تحلیل رشد در صف قرار گرفت و پس از اتمام، فرصت‌ها و ریسک‌ها به‌روزرسانی می‌شوند.');
    }
}
