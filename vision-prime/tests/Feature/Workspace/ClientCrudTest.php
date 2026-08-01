<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_agency_admin_can_create_update_and_archive_client(): void
    {
        [$admin, $organization] = $this->userWithMembership('agency-admin');

        $createResponse = $this->actingAs($admin)->post('/app/clients', [
            'name' => 'کلینیک آفتاب',
            'contact_name' => 'سارا احمدی',
            'contact_email' => 'sara@aftab.test',
            'contact_phone' => '09120000000',
        ]);

        $client = Client::query()->where('organization_id', $organization->getKey())->firstOrFail();
        $createResponse->assertRedirect("/app/clients/{$client->getKey()}");
        $this->assertSame('سارا احمدی', $client->contact['name']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.created', 'subject_id' => $client->getKey()]);

        $this->actingAs($admin)->put("/app/clients/{$client->getKey()}", [
            'name' => 'کلینیک آفتاب نو',
            'contact_name' => 'مینا احمدی',
            'contact_email' => 'mina@aftab.test',
            'contact_phone' => '',
        ])->assertRedirect("/app/clients/{$client->getKey()}");

        $this->assertDatabaseHas('clients', ['id' => $client->getKey(), 'name' => 'کلینیک آفتاب نو']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.updated', 'subject_id' => $client->getKey()]);

        $this->actingAs($admin)->delete("/app/clients/{$client->getKey()}")->assertRedirect('/app/clients');
        $this->assertSoftDeleted('clients', ['id' => $client->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.archived', 'subject_id' => $client->getKey()]);
    }

    public function test_agency_admin_can_assign_an_eligible_client_portal_member(): void
    {
        [$admin, $organization] = $this->userWithMembership('agency-admin');
        [$clientUser] = $this->userWithMembership('client-approver', $organization);
        $client = $this->client($organization);

        $this->actingAs($admin)->post("/app/clients/{$client->getKey()}/assignments", [
            'email' => $clientUser->email,
            'portal_role' => 'approver',
        ])->assertRedirect();

        $this->assertDatabaseHas('client_user_assignments', [
            'client_id' => $client->getKey(),
            'user_id' => $clientUser->getKey(),
            'portal_role' => 'approver',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'client.user_assigned', 'subject_id' => $client->getKey()]);
    }

    public function test_user_cannot_view_client_from_another_organization(): void
    {
        [$admin] = $this->userWithMembership('agency-admin');
        [, $otherOrganization] = $this->userWithMembership('agency-admin');
        $otherClient = $this->client($otherOrganization);

        $this->actingAs($admin)->get("/app/clients/{$otherClient->getKey()}")->assertForbidden();
    }

    /** @return array{User, Organization} */
    private function userWithMembership(string $roleKey, ?Organization $organization = null): array
    {
        $user = User::factory()->create();
        $organization ??= Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'سازمان '.Str::random(6),
            'slug' => 'organization-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->where('key', $roleKey)->valueOrFail('id'),
            'status' => 'active',
        ]);

        return [$user, $organization];
    }

    private function client(Organization $organization): Client
    {
        return Client::query()->create([
            'organization_id' => $organization->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'مشتری '.Str::random(6),
            'status' => 'active',
        ]);
    }
}
