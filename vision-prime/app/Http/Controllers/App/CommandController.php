<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class CommandController extends Controller
{
    public function index(CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $commands = \DB::table('commands')->whereIn('site_id', $siteIds)->latest('id')->paginate(50);

        return Inertia::render('App/Commands/Index', ['commands' => $commands]);
    }

    public function show(int $command, CurrentOrganization $org): Response
    {
        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $item = \DB::table('commands')->whereIn('site_id', $siteIds)->where('id', $command)->firstOrFail();
        $approvals = \DB::table('command_approvals')->where('command_id', $item->id)->get();
        $logs = \DB::table('command_execution_logs')->where('command_id', $item->id)->get();
        $snapshots = \DB::table('rollback_snapshots')->where('command_id', $item->id)->get(['id', 'target_ref', 'status', 'expires_at']);

        return Inertia::render('App/Commands/Show', ['command' => $item, 'approvals' => $approvals, 'logs' => $logs, 'snapshots' => $snapshots]);
    }
}
