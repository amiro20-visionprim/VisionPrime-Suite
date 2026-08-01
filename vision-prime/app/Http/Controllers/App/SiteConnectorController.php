<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SiteConnectorController extends Controller
{
    public function show(Site $site): Response
    {
        Gate::authorize('view', $site);
        $connection = \DB::table('site_connections')->where('site_id', $site->id)->first();

        return Inertia::render('App/Sites/Connector', [
            'site' => ['id' => $site->id, 'name' => $site->name, 'canonicalUrl' => $site->canonical_url],
            'connection' => $connection === null ? null : [
                'status' => $connection->status,
                'platformUrl' => $connection->platform_url,
                'pluginVersion' => $connection->plugin_version,
                'lastSeenAt' => $connection->last_seen_at,
                'health' => json_decode($connection->health ?? '{}', true),
            ],
        ]);
    }
}
