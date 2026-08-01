<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Content\Jobs\SyncSiteContent;
use App\Domains\Content\Models\SyncRun;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SiteSyncController extends Controller
{
    public function store(Site $site): RedirectResponse
    {
        Gate::authorize('update', $site);
        $run = SyncRun::query()->create(['site_id' => $site->id, 'type' => 'content', 'status' => 'queued']);
        SyncSiteContent::dispatch($run->id);

        return back()->with('status', 'همگام‌سازی محتوا در صف قرار گرفت.');
    }
}
