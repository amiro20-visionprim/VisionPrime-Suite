<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Models\Membership;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\ClientUserAssignment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AssignClientUser
{
    public function handle(Client $client, User $user, string $portalRole): ClientUserAssignment
    {
        $membership = Membership::query()
            ->where('organization_id', $client->organization_id)
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->with('role:id,key')
            ->first();

        if ($membership === null || ! $this->roleAllowsAssignment($membership->role?->key, $portalRole)) {
            throw ValidationException::withMessages([
                'email' => 'این کاربر باید عضو فعال سازمان با نقش مناسب پرتال مشتری باشد.',
            ]);
        }

        $assignment = ClientUserAssignment::query()->updateOrCreate(
            ['client_id' => $client->getKey(), 'user_id' => $user->getKey()],
            ['portal_role' => $portalRole],
        );

        $this->recordAuditLog->handle(
            action: 'client.user_assigned',
            subject: $client,
            after: ['user_id' => $user->getKey(), 'portal_role' => $portalRole],
        );

        return $assignment;
    }

    private function roleAllowsAssignment(?string $membershipRole, string $portalRole): bool
    {
        return match ($portalRole) {
            'viewer' => in_array($membershipRole, ['client-viewer', 'client-approver'], true),
            'approver' => $membershipRole === 'client-approver',
            default => false,
        };
    }

    public function __construct(private readonly RecordAuditLog $recordAuditLog) {}
}
