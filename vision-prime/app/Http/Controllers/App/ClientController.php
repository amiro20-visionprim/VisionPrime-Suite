<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Actions\ArchiveClient;
use App\Domains\Workspace\Actions\AssignClientUser;
use App\Domains\Workspace\Actions\CreateClient;
use App\Domains\Workspace\Actions\RemoveClientUserAssignment;
use App\Domains\Workspace\Actions\UpdateClient;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\ClientUserAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\AssignClientUserRequest;
use App\Http\Requests\Workspace\StoreClientRequest;
use App\Http\Requests\Workspace\UpdateClientRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->get();
        Gate::authorize('viewAny', [Client::class, $organization]);

        $clients = Client::query()
            ->where('organization_id', $organization->getKey())
            ->withCount(['projects', 'userAssignments'])
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (Client $client): array => $this->listItem($client));

        return Inertia::render('App/Clients/Index', ['clients' => $clients]);
    }

    public function create(CurrentOrganization $currentOrganization): Response
    {
        Gate::authorize('create', [Client::class, $currentOrganization->get()]);

        return Inertia::render('App/Clients/Create');
    }

    public function store(StoreClientRequest $request, CreateClient $createClient): RedirectResponse
    {
        Gate::authorize('create', [Client::class, app(CurrentOrganization::class)->get()]);
        $client = $createClient->handle($request->validated());

        return redirect()->route('app.clients.show', $client)->with('status', 'مشتری با موفقیت ایجاد شد.');
    }

    public function show(Client $client): Response
    {
        Gate::authorize('view', $client);
        $client->loadCount('projects')->load([
            'userAssignments.user:id,name,email',
        ]);

        return Inertia::render('App/Clients/Show', [
            'client' => $this->detailItem($client),
            'assignments' => $client->userAssignments->map(fn (ClientUserAssignment $assignment): array => [
                'id' => $assignment->getKey(),
                'userName' => $assignment->user->name,
                'userEmail' => $assignment->user->email,
                'portalRole' => $assignment->portal_role,
            ])->values(),
            'canManage' => Gate::allows('update', $client),
        ]);
    }

    public function edit(Client $client): Response
    {
        Gate::authorize('update', $client);

        return Inertia::render('App/Clients/Edit', ['client' => $this->detailItem($client)]);
    }

    public function update(UpdateClientRequest $request, Client $client, UpdateClient $updateClient): RedirectResponse
    {
        Gate::authorize('update', $client);
        $updateClient->handle($client, $request->validated());

        return redirect()->route('app.clients.show', $client)->with('status', 'اطلاعات مشتری به‌روزرسانی شد.');
    }

    public function destroy(Client $client, ArchiveClient $archiveClient): RedirectResponse
    {
        Gate::authorize('delete', $client);
        $archiveClient->handle($client);

        return redirect()->route('app.clients.index')->with('status', 'مشتری بایگانی شد.');
    }

    public function assignUser(AssignClientUserRequest $request, Client $client, AssignClientUser $assignClientUser): RedirectResponse
    {
        Gate::authorize('update', $client);
        $user = User::query()->where('email', $request->string('email')->lower()->toString())->firstOrFail();
        $assignClientUser->handle($client, $user, $request->string('portal_role')->toString());

        return back()->with('status', 'دسترسی پرتال مشتری به‌روزرسانی شد.');
    }

    public function removeUserAssignment(Client $client, ClientUserAssignment $assignment, RemoveClientUserAssignment $removeClientUserAssignment): RedirectResponse
    {
        Gate::authorize('update', $client);
        $removeClientUserAssignment->handle($client, $assignment);

        return back()->with('status', 'دسترسی کاربر حذف شد.');
    }

    /** @return array<string, mixed> */
    private function listItem(Client $client): array
    {
        return [
            'id' => $client->getKey(),
            'publicId' => $client->public_id,
            'name' => $client->name,
            'status' => $client->status,
            'projectsCount' => $client->projects_count,
            'portalUsersCount' => $client->user_assignments_count,
            'updatedAt' => $client->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function detailItem(Client $client): array
    {
        return [
            'id' => $client->getKey(),
            'publicId' => $client->public_id,
            'name' => $client->name,
            'status' => $client->status,
            'contactName' => $client->contact['name'] ?? null,
            'contactEmail' => $client->contact['email'] ?? null,
            'contactPhone' => $client->contact['phone'] ?? null,
            'projectsCount' => $client->projects_count ?? $client->projects()->count(),
            'createdAt' => $client->created_at?->toIso8601String(),
            'updatedAt' => $client->updated_at?->toIso8601String(),
        ];
    }
}
