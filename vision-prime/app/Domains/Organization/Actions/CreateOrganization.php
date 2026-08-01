<?php

declare(strict_types=1);

namespace App\Domains\Organization\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateOrganization
{
    public function __construct(private readonly RecordAuditLog $recordAuditLog) {}

    public function handle(User $user, string $name): Organization
    {
        return DB::transaction(function () use ($user, $name): Organization {
            $organization = Organization::query()->create([
                'public_id' => (string) Str::ulid(),
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'status' => 'active',
            ]);

            $agencyAdminRole = Role::query()->where('key', 'agency-admin')->first();

            if ($agencyAdminRole === null) {
                throw new RuntimeException('The agency-admin role must be seeded before creating an organization.');
            }

            Membership::query()->create([
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'role_id' => $agencyAdminRole->getKey(),
                'status' => 'active',
            ]);

            $this->recordAuditLog->handle(
                action: 'organization.created',
                subject: $organization,
                after: ['name' => $organization->name, 'slug' => $organization->slug],
                organization: $organization,
            );

            return $organization;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'organization';
        $slug = $base;
        $suffix = 2;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
