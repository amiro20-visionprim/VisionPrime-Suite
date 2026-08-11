<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Contracts\CurrentClient;
use App\Domains\Workspace\Services\ClientAccessScope;
use App\Domains\Workspace\Services\OrganizationPermission;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
            ],
            'auth' => [
                'user' => fn (): ?array => $request->user() === null ? null : [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
            ],
            'availableOrganizations' => fn (): array => $request->user() === null ? [] : $request->user()->memberships()
                ->with('organization')
                ->where('status', 'active')
                ->get()
                ->map(fn ($membership): array => [
                    'id' => $membership->organization->getKey(),
                    'publicId' => $membership->organization->public_id,
                    'name' => $membership->organization->name,
                    'slug' => $membership->organization->slug,
                ])->all(),
            'currentOrganization' => fn (): ?array => app(CurrentOrganization::class)->has() ? [
                'publicId' => app(CurrentOrganization::class)->get()->public_id,
                'name' => app(CurrentOrganization::class)->get()->name,
                'slug' => app(CurrentOrganization::class)->get()->slug,
            ] : null,
            'availableClients' => fn (): array => $request->user() === null || ! app(CurrentOrganization::class)->has() ? [] : app(ClientAccessScope::class)
                ->visibleQuery($request->user(), app(CurrentOrganization::class)->get())
                ->orderBy('name')
                ->get()
                ->map(fn ($client): array => [
                    'id' => $client->getKey(),
                    'publicId' => $client->public_id,
                    'name' => $client->name,
                ])->all(),
            'currentClient' => fn (): ?array => app(CurrentClient::class)->has() ? [
                'publicId' => app(CurrentClient::class)->get()->public_id,
                'name' => app(CurrentClient::class)->get()->name,
            ] : null,
            'permissions' => fn (): array => $request->user() === null || ! app(CurrentOrganization::class)->has() ? [] : app(OrganizationPermission::class)
                ->allPermissions($request->user(), app(CurrentOrganization::class)->get()),
            'app' => [
                'name' => config('app.name'),
                'locale' => config('vision-prime.default_locale'),
                'timezone' => config('vision-prime.default_timezone'),
            ],
        ];
    }
}
