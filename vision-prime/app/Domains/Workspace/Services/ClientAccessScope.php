<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Services;

use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ClientAccessScope
{
    public function canAccess(User $user, Client $client): bool
    {
        if ($this->organizationPermission->allows($user, $client->organization, 'client.view.organization')) {
            return true;
        }

        return $client->portalUsers()->whereKey($user->getKey())->exists();
    }

    /** @return Builder<Client> */
    public function visibleQuery(User $user, Organization $organization): Builder
    {
        $query = Client::query()->where('organization_id', $organization->getKey());

        if ($this->organizationPermission->allows($user, $organization, 'client.view.organization')) {
            return $query;
        }

        return $query->whereHas('portalUsers', fn (Builder $users) => $users->whereKey($user->getKey()));
    }

    public function __construct(private readonly OrganizationPermission $organizationPermission) {}
}
