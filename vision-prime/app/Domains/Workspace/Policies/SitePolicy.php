<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Policies;

use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Site;
use App\Domains\Workspace\Services\OrganizationPermission;
use App\Models\User;

class SitePolicy
{
    public function __construct(private readonly OrganizationPermission $organizationPermission) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->organizationPermission->allows($user, $organization, 'site.view.organization')
            || $this->organizationPermission->allows($user, $organization, 'site.view.assigned');
    }

    public function view(User $user, Site $site): bool
    {
        return $this->viewAny($user, Organization::query()->findOrFail($site->organization_id));
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->organizationPermission->allows($user, $organization, 'site.manage.organization');
    }

    public function update(User $user, Site $site): bool
    {
        return $this->create($user, Organization::query()->findOrFail($site->organization_id));
    }

    public function delete(User $user, Site $site): bool
    {
        return $this->update($user, $site);
    }
}
