<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\ClientUserAssignment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_client_access_is_scoped_to_membership_permission_or_explicit_assignment(): void
    {
        $organization = $this->organization('organization-a');
        $otherOrganization = $this->organization('organization-b');
        $client = $this->client($organization, 'مشتری قابل مشاهده');
        $otherClient = $this->client($organization, 'مشتری خصوصی');
        $externalClient = $this->client($otherOrganization, 'مشتری سازمان دیگر');
        $agencyAdmin = User::factory()->create();
        $clientViewer = User::factory()->create();

        $this->membership($agencyAdmin, $organization, 'agency-admin');
        $this->membership($clientViewer, $organization, 'client-viewer');
        ClientUserAssignment::query()->create([
            'client_id' => $client->getKey(),
            'user_id' => $clientViewer->getKey(),
            'portal_role' => 'viewer',
        ]);

        $this->assertTrue(Gate::forUser($agencyAdmin)->allows('view', $client));
        $this->assertFalse(Gate::forUser($agencyAdmin)->allows('view', $externalClient));
        $this->assertTrue(Gate::forUser($clientViewer)->allows('view', $client));
        $this->assertFalse(Gate::forUser($clientViewer)->allows('view', $otherClient));
        $this->assertFalse(Gate::forUser($clientViewer)->allows('create', [$organization]));
    }

    private function organization(string $slug): Organization
    {
        return Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => $slug,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    private function client(Organization $organization, string $name): Client
    {
        return Client::query()->create([
            'organization_id' => $organization->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function membership(User $user, Organization $organization, string $roleKey): void
    {
        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->where('key', $roleKey)->valueOrFail('id'),
            'status' => 'active',
        ]);
    }
}
