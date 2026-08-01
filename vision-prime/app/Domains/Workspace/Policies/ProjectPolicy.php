<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Policies;

use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Services\OrganizationPermission;
use App\Models\User;

class ProjectPolicy
{
    public function __construct(private readonly OrganizationPermission $organizationPermission) {}

    public function viewAny(User $user, Organization $organization): bool
    {
        return $this->organizationPermission->allows($user, $organization, 'project.view.organization');
    }

    public function view(User $user, Project $project): bool
    {
        return $this->viewAny($user, $project->organization);
    }

    public function create(User $user, Organization $organization): bool
    {
        return $this->organizationPermission->allows($user, $organization, 'project.manage.organization');
    }

    public function update(User $user, Project $project): bool
    {
        return $this->create($user, $project->organization);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->update($user, $project);
    }
}
