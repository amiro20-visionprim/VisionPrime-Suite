<?php

declare(strict_types=1);

namespace App\Domains\Organization\Actions;

use App\Domains\Organization\Models\Membership;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class SwitchCurrentOrganization
{
    public function handle(User $user, int $organizationId): void
    {
        $isActiveMember = Membership::query()
            ->where('user_id', $user->getKey())
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->exists();

        if (! $isActiveMember) {
            throw new AuthorizationException('You do not have access to this organization.');
        }

        session(['current_organization_id' => $organizationId]);
    }
}
