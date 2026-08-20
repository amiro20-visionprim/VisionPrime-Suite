<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
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

    /**
     * قطع اتصال سایت از وردپرس — فقط ادمین و سوپر ادمین.
     * اتصال (site_connections)، توکن‌های pairing، و url_profiles پاک میشن.
     * خود سایت حذف نمیشه.
     */
    public function disconnect(Site $site, CurrentOrganization $c): RedirectResponse
    {
        Gate::authorize('delete', $site);

        $user = request()->user();
        $isAdmin = $user?->memberships()->where('status', 'active')->with('role')->get()->contains(fn ($m) => in_array($m->role?->key, ['agency-admin', 'super-admin'])) ?? false;
        if ($user !== null && ! $user->isSuperAdmin() && ! $isAdmin) {
            abort(403, 'فقط مدیر سیستم یا ادمین می‌تواند اتصال را قطع کند.');
        }

        \DB::transaction(function () use ($site): void {
            \DB::table('url_profiles')->where('site_id', $site->id)->delete();
            \DB::table('keyword_insights')->where('site_id', $site->id)->delete();
            \DB::table('content_drafts')->where('site_id', $site->id)->delete();
            \DB::table('site_connector_tokens')->where('site_id', $site->id)->delete();
            \DB::table('site_connections')->where('site_id', $site->id)->delete();
        });

        return back()->with('status', 'اتصال سایت قطع شد. داده‌های همگام‌سازی حذف شدند.');
    }
}
