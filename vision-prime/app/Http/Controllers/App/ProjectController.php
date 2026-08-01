<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Actions\ArchiveProject;
use App\Domains\Workspace\Actions\CreateProject;
use App\Domains\Workspace\Actions\UpdateProject;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreProjectRequest;
use App\Http\Requests\Workspace\UpdateProjectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(CurrentOrganization $context): Response
    {
        $org = $context->get();
        Gate::authorize('viewAny', [Project::class, $org]);
        $projects = Project::query()->where('organization_id', $org->getKey())->with('client:id,name')->withCount('sites')->orderBy('name')->paginate(20)->through(fn (Project $p) => $this->item($p));

        return Inertia::render('App/Projects/Index', ['projects' => $projects]);
    }

    public function create(CurrentOrganization $context): Response
    {
        $org = $context->get();
        Gate::authorize('create', [Project::class, $org]);

        return Inertia::render('App/Projects/Create', ['clients' => $this->clients($org)]);
    }

    public function store(StoreProjectRequest $request, CreateProject $create): RedirectResponse
    {
        $client = Client::query()->findOrFail($request->integer('client_id'));
        Gate::authorize('update', $client);
        $this->ensureCurrentOrg($client);
        $project = $create->handle($client, $request->string('name')->trim()->toString(), $request->string('objective')->trim()->toString() ?: null);

        return redirect()->route('app.projects.show', $project)->with('status', 'پروژه با موفقیت ایجاد شد.');
    }

    public function show(Project $project): Response
    {
        Gate::authorize('view', $project);
        $project->load('client:id,name')->loadCount('sites');

        return Inertia::render('App/Projects/Show', ['project' => $this->item($project)]);
    }

    public function edit(Project $project, CurrentOrganization $context): Response
    {
        Gate::authorize('update', $project);

        return Inertia::render('App/Projects/Edit', ['project' => $this->item($project), 'clients' => $this->clients($context->get())]);
    }

    public function update(UpdateProjectRequest $request, Project $project, UpdateProject $update): RedirectResponse
    {
        Gate::authorize('update', $project);
        $client = Client::query()->findOrFail($request->integer('client_id'));
        $this->ensureCurrentOrg($client);
        abort_unless($client->getKey() === $project->client_id, 422);
        $update->handle($project, $request->string('name')->trim()->toString(), $request->string('objective')->trim()->toString() ?: null);

        return redirect()->route('app.projects.show', $project)->with('status', 'پروژه به‌روزرسانی شد.');
    }

    public function destroy(Project $project, ArchiveProject $archive): RedirectResponse
    {
        Gate::authorize('delete', $project);
        $archive->handle($project);

        return redirect()->route('app.projects.index')->with('status', 'پروژه بایگانی شد.');
    }

    private function ensureCurrentOrg(Client $client): void
    {
        abort_unless($client->organization_id === app(CurrentOrganization::class)->id(), 404);
    }

    private function clients($org): array
    {
        return Client::query()->where('organization_id', $org->getKey())->orderBy('name')->get(['id', 'name'])->map(fn (Client $c) => ['id' => $c->getKey(), 'name' => $c->name])->all();
    }

    private function item(Project $p): array
    {
        return ['id' => $p->getKey(), 'name' => $p->name, 'objective' => $p->objective, 'status' => $p->status, 'clientId' => $p->client_id, 'clientName' => $p->client?->name, 'sitesCount' => $p->sites_count ?? $p->sites()->count(), 'updatedAt' => $p->updated_at?->toIso8601String()];
    }
}
