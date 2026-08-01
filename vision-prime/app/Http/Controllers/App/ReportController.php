<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Reporting\Actions\BuildExecutiveReport;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $reports = \DB::table('reports')->whereIn('site_id', $siteIds)->latest('id')->paginate(50);

        return Inertia::render('App/Reports/Index', ['reports' => $reports]);
    }

    public function store(Request $request, BuildExecutiveReport $builder): RedirectResponse
    {
        $data = $request->validate(['site_id' => ['required', 'exists:sites,id'], 'type' => ['required', 'string'], 'period_start' => ['required', 'date'], 'period_end' => ['required', 'date']]);
        $site = Site::findOrFail($data['site_id']);
        abort_unless($site->organization_id === app(CurrentOrganization::class)->id(), 404);
        $builder->handle($site, $data['type'], $data['period_start'], $data['period_end'], $request->user()->id);

        return back()->with('status', 'گزارش Draft ایجاد شد.');
    }
}
