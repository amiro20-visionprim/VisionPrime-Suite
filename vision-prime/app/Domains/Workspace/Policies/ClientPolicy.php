<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Policies;

use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Services\ClientAccessScope;
use App\Domains\Workspace\Services\OrganizationPermission;
use App\Models\User;

class ClientPolicy
{
    public function __construct(
        private readonly OrganizationPermission $organizationPermission,
        private readonly ClientAccessScope $clientAccessScope,
    ) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->organizationPermission->allows($user, $organization, 'client.view.organization');
    }

    public function view(User $user, Client $client): bool
    {
        return $this->clientAccessScope->canAccess($user, $client);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->organizationPermission->allows($user, $organization, 'client.manage.organization');
    }

    public function update(User $user, Client $client): bool
    {
        return $this->organizationPermission->allows($user, $client->organization, 'client.manage.organization');
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->update($user, $client);
    }
}
