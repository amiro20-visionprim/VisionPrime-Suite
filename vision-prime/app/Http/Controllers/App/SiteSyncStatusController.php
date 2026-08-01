<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Content\Models\SyncRun;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SiteSyncStatusController extends Controller
{
    public function show(Site $site): Response
    {
        Gate::authorize('view', $site);
        $run = SyncRun::query()->where('site_id', $site->id)->latest('id')->with('items')->first();

        return Inertia::render('App/Sites/Sync', ['site' => ['id' => $site->id, 'name' => $site->name], 'run' => $run ? ['status' => $run->status, 'summary' => $run->summary, 'error' => $run->error, 'startedAt' => $run->started_at?->toIso8601String(), 'finishedAt' => $run->finished_at?->toIso8601String(), 'failedItems' => $run->items->where('status', 'failed')->map(fn ($i) => ['url' => $i->url, 'error' => $i->error])->values()] : null]);
    }
}
