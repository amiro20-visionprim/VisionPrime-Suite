<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Services;

use App\Domains\Organization\Models\Organization;
use App\Models\User;

class OrganizationPermission
{
    public function allows(User $user, Organization $organization, string $permission): bool
    {
        return $user->memberships()
            ->where('organization_id', $organization->getKey())
            ->where('status', 'active')
            ->whereHas('role.permissions', fn ($query) => $query->where('key', $permission))
            ->exists();
    }
}
