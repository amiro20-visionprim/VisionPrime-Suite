<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommandDecisionFromListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function setUpWorkspace(): array
    {
        $organization = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'R', 'slug' => 'r-'.Str::random(5), 'status' => 'active']);
        $admin = User::factory()->create();
        $viewer = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $viewer->id, 'role_id' => Role::query()->where('key', 'client-viewer')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);

        $commandId = DB::table('commands')->insertGetId([
            'site_id' => $site->id,
            'source_type' => 'recommendation',
            'type' => 'update_meta_title',
            'risk_tier' => 'R2',
            'payload' => json_encode(['url' => 'https://e.ir/x/', 'title' => 'new']),
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'pending_approval',
            'expires_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$organization, $admin, $viewer, $site, $commandId];
    }

    public function test_team_member_with_approve_permission_can_approve_command(): void
    {
        [$organization, $admin, , , $commandId] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/commands/{$commandId}/decision", ['decision' => 'approved'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('commands', ['id' => $commandId, 'status' => 'approved']);
        $this->assertDatabaseHas('command_approvals', ['command_id' => $commandId, 'decision' => 'approved']);
    }

    public function test_team_member_can_reject_command(): void
    {
        [$organization, $admin, , , $commandId] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/commands/{$commandId}/decision", ['decision' => 'rejected'])
            ->assertRedirect();

        $this->assertDatabaseHas('commands', ['id' => $commandId, 'status' => 'cancelled']);
    }

    public function test_member_without_approve_permission_is_forbidden(): void
    {
        [$organization, , $viewer, , $commandId] = $this->setUpWorkspace();

        $this->actingAs($viewer)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/commands/{$commandId}/decision", ['decision' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('commands', ['id' => $commandId, 'status' => 'pending_approval']);
    }

    public function test_cannot_decide_command_of_another_organization(): void
    {
        [$organization, $admin] = $this->setUpWorkspace();

        $foreignOrg = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'F', 'slug' => 'f-'.Str::random(5), 'status' => 'active']);
        $foreignClient = Client::query()->create(['organization_id' => $foreignOrg->id, 'public_id' => (string) Str::ulid(), 'name' => 'FC', 'status' => 'active']);
        $foreignProject = Project::query()->create(['organization_id' => $foreignOrg->id, 'client_id' => $foreignClient->id, 'public_id' => (string) Str::ulid(), 'name' => 'FP', 'status' => 'active']);
        $foreignSite = Site::query()->create(['organization_id' => $foreignOrg->id, 'project_id' => $foreignProject->id, 'public_id' => (string) Str::ulid(), 'name' => 'FS', 'canonical_url' => 'https://f.ir', 'status' => 'active']);
        $commandId = DB::table('commands')->insertGetId([
            'site_id' => $foreignSite->id,
            'source_type' => 'x',
            'type' => 'update_meta_title',
            'risk_tier' => 'R1',
            'payload' => '{}',
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'pending_approval',
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/commands/{$commandId}/decision", ['decision' => 'approved'])
            ->assertNotFound();
    }
}
