<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Actions\ArchiveSite;
use App\Domains\Workspace\Actions\CreateSite;
use App\Domains\Workspace\Actions\UpdateSite;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreSiteRequest;
use App\Http\Requests\Workspace\UpdateSiteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(CurrentOrganization $c): Response
    {
        $o = $c->get();
        Gate::authorize('viewAny', [Site::class, $o]);
        $sites = Site::query()->where('organization_id', $o->id)->with('project.client:id,name')->orderBy('name')->paginate(20)->through(fn (Site $site) => $this->item($site));

        return Inertia::render('App/Sites/Index', ['sites' => $sites]);
    }

    public function create(CurrentOrganization $c): Response
    {
        Gate::authorize('create', [Site::class, $c->get()]);

        return Inertia::render('App/Sites/Create', ['projects' => $this->projects($c)]);
    }

    public function store(StoreSiteRequest $r, CreateSite $a): RedirectResponse
    {
        $p = Project::query()->findOrFail($r->integer('project_id'));
        abort_unless($p->organization_id === app(CurrentOrganization::class)->id(), 404);
        Gate::authorize('create', [Site::class, app(CurrentOrganization::class)->get()]);
        $site = $a->handle($p, $r->validated());

        return redirect()->route('app.sites.show', $site)->with('status', 'سایت ایجاد شد؛ اکنون می‌توانید منابع داده را متصل کنید.');
    }

    public function show(Site $site): Response
    {
        Gate::authorize('view', $site);
        $site->load('project.client:id,name');
        $user = request()->user();

        return Inertia::render('App/Sites/Show', [
            'site' => $this->item($site),
            'gsc' => $this->gscStatus($site),
            'connector' => $this->connectorStatus($site),
            'content' => $this->contentSummary($site),
            'isSuperAdmin' => $user?->isSuperAdmin() ?? false,
            'isAdmin' => $user?->memberships()->where('status', 'active')->with('role')->get()->contains(fn ($m) => in_array($m->role?->key, ['agency-admin', 'super-admin'])) ?? false,
        ]);
    }

    public function edit(Site $site, CurrentOrganization $c): Response
    {
        Gate::authorize('update', $site);

        return Inertia::render('App/Sites/Edit', ['site' => $this->item($site), 'projects' => $this->projects($c)]);
    }

    public function update(UpdateSiteRequest $r, Site $site, UpdateSite $a): RedirectResponse
    {
        Gate::authorize('update', $site);
        $p = Project::query()->findOrFail($r->integer('project_id'));
        abort_unless($p->id === $site->project_id && $p->organization_id === app(CurrentOrganization::class)->id(), 422);
        $a->handle($site, $r->validated());

        return back()->with('status', 'سایت به‌روزرسانی شد.');
    }

    public function destroy(Site $site, ArchiveSite $a): RedirectResponse
    {
        Gate::authorize('delete', $site);
        $a->handle($site);

        return redirect()->route('app.sites.index')->with('status', 'سایت بایگانی شد.');
    }

    private function projects(CurrentOrganization $c): array
    {
        return Project::query()->where('organization_id', $c->id())->with('client:id,name')->orderBy('name')->get()->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name, 'clientName' => $p->client->name])->all();
    }

    private function item(Site $site): array
    {
        return ['id' => $site->id, 'name' => $site->name, 'canonicalUrl' => $site->canonical_url, 'projectId' => $site->project_id, 'projectName' => $site->project?->name, 'clientName' => $site->project?->client?->name, 'locale' => $site->locale, 'timezone' => $site->timezone, 'businessImportance' => $site->business_importance, 'status' => $site->status];
    }

    private function gscStatus(Site $site): array
    {
        $property = \DB::table('gsc_properties')->where('site_id', $site->id)->first();
        if ($property === null) {
            return ['connected' => false, 'property' => null, 'accountEmail' => null, 'latestRun' => null];
        }
        $account = \DB::table('gsc_accounts')->where('id', $property->gsc_account_id)->first();
        $run = \DB::table('gsc_import_runs')->where('gsc_property_id', $property->id)->latest('id')->first();

        return [
            'connected' => true,
            'property' => ['id' => $property->id, 'uri' => $property->property_uri, 'type' => $property->property_type, 'status' => $property->status],
            'accountEmail' => $account?->email,
            'latestRun' => $run === null ? null : ['status' => $run->status, 'summary' => json_decode($run->summary ?? '{}', true), 'error' => $run->error, 'startedAt' => $run->started_at, 'finishedAt' => $run->finished_at],
        ];
    }

    private function connectorStatus(Site $site): array
    {
        $connection = \DB::table('site_connections')->where('site_id', $site->id)->first();
        if ($connection === null) {
            return ['connected' => false, 'status' => null, 'platformUrl' => null, 'pluginVersion' => null, 'lastSeenAt' => null];
        }

        return ['connected' => true, 'status' => $connection->status, 'platformUrl' => $connection->platform_url, 'pluginVersion' => $connection->plugin_version, 'lastSeenAt' => $connection->last_seen_at];
    }

    private function contentSummary(Site $site): array
    {
        $profiles = \DB::table('url_profiles')->where('site_id', $site->id)->get();
        $total = $profiles->count();
        $byType = $profiles->groupBy('content_type')->map(fn ($items) => $items->count())->toArray();
        $recent = $profiles->sortByDesc('last_synced_at')->take(10)->values()->map(fn ($p) => [
            'id' => (int) $p->id,
            'url' => $p->canonical_url,
            'type' => $p->content_type,
            'status' => $p->post_status,
            'lastSyncedAt' => $p->last_synced_at,
        ])->toArray();

        return [
            'total' => $total,
            'byType' => $byType,
            'recent' => $recent,
        ];
    }
}
