<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ReportPublishController extends Controller
{
    public function store(int $report, CurrentOrganization $org, RecordAuditLog $audit): RedirectResponse
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $item = DB::table('reports')->whereIn('site_id', $siteIds)->where('id', $report)->firstOrFail();

        if ($item->status === 'published') {
            return back()->with('status', 'این گزارش قبلاً منتشر شده است.');
        }

        DB::table('reports')->where('id', $item->id)->update([
            'status' => 'published',
            'published_at' => now(),
            'updated_at' => now(),
        ]);

        $audit->handle(
            action: 'report.published',
            after: ['report_id' => $item->id, 'type' => $item->type],
        );

        return back()->with('status', 'گزارش منتشر شد و در پرتال مشتری قابل مشاهده است.');
    }
}
