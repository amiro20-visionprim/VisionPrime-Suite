<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\ClientUserAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_client_viewer_can_access_the_client_portal(): void
    {
        $user = User::factory()->create();
        $organization = $this->attachMembership($user, 'client-viewer');
        $client = Client::query()->create([
            'organization_id' => $organization->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'مشتری تخصیص‌داده‌شده',
            'status' => 'active',
        ]);
        ClientUserAssignment::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $user->getKey(),
            'portal_role' => 'viewer',
        ]);

        $this->actingAs($user)->get('/client/dashboard')->assertOk();
    }

    public function test_agency_admin_can_preview_the_client_portal(): void
    {
        $user = User::factory()->create();
        $this->attachMembership($user, 'agency-admin');

        $this->actingAs($user)->get('/client/dashboard')->assertOk();
    }

    public function test_operational_roles_cannot_access_client_portal_without_client_role(): void
    {
        $user = User::factory()->create();
        $this->attachMembership($user, 'seo-manager');

        $this->actingAs($user)->get('/client/dashboard')->assertForbidden();
    }

    private function attachMembership(User $user, string $roleKey): Organization
    {
        $organization = Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'سازمان آزمون',
            'slug' => 'organization-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->where('key', $roleKey)->valueOrFail('id'),
            'status' => 'active',
        ]);

        return $organization;
    }
}
