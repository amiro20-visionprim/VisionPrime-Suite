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
        // Return a fixed version from config. Change ASSET_VERSION in .env
        // on every frontend deploy to force browsers to reload.
        return config('app.asset_version', '1.0.0');
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'status' => fn (): ?string => $request->session()->get('status'),
                // One-time pairing token shown right after creation on the connector page.
                'pairingToken' => fn (): ?string => $request->session()->get('pairingToken'),
                'pairingTokenExpiresAt' => fn (): ?string => $request->session()->get('pairingTokenExpiresAt'),
                // MFA backup codes — one-time: pulled (removed) on first read.
                'mfaBackupCodes' => fn (): ?array => $request->session()->pull('mfa_backup_codes'),
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
            'currentRole' => fn (): ?string => $request->user() === null || ! app(CurrentOrganization::class)->has() ? null : $request->user()->memberships()
                ->where('organization_id', app(CurrentOrganization::class)->get()->getKey())
                ->where('status', 'active')
                ->with('role')
                ->get()
                ->first()
                ?->role?->key,
            'notificationCount' => fn (): int => $request->user() === null ? 0 : $request->user()->unreadNotifications()->count(),
            'impersonating' => fn (): ?array => $request->user() === null || ! $request->session()->has('platform_impersonating') ? null : [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
            'app' => [
                'name' => config('app.name'),
                'locale' => config('vision-prime.default_locale'),
                'timezone' => config('vision-prime.default_timezone'),
            ],
        ];
    }
}
