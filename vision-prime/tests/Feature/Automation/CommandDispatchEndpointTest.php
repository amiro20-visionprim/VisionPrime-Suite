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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommandDispatchEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private Site $site;

    private int $commandId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->organization = Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'سازمان آزمون',
            'slug' => 'org-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['email' => 'admin@test.ir']);
        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $this->admin->getKey(),
            'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'),
            'status' => 'active',
        ]);

        $client = Client::query()->create(['organization_id' => $this->organization->getKey(), 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $this->organization->getKey(), 'client_id' => $client->getKey(), 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $this->site = Site::query()->create(['organization_id' => $this->organization->getKey(), 'project_id' => $project->getKey(), 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);

        \DB::table('site_connections')->insert([
            'site_id' => $this->site->getKey(),
            'status' => 'connected',
            'platform_url' => 'https://wp.test',
            'secret_ciphertext' => Crypt::encryptString('test-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->commandId = \DB::table('commands')->insertGetId([
            'site_id' => $this->site->getKey(),
            'source_type' => 'test',
            'type' => 'update_meta_title',
            'risk_tier' => 'R1',
            'payload' => json_encode(['title' => 'عنوان جدید']),
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'approved',
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_authorized_user_executes_approved_command(): void
    {
        Http::fake([
            'https://wp.test/wp-json/vision-prime/v1/commands' => Http::response(['ok' => true, 'result' => 'done'], 200),
        ]);

        $this->actingAs($this->admin)
            ->post("/app/commands/{$this->commandId}/dispatch")
            ->assertRedirect();

        $this->assertDatabaseHas('commands', ['id' => $this->commandId, 'status' => 'executed']);
        $this->assertDatabaseHas('command_execution_logs', ['command_id' => $this->commandId, 'status' => 'executed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'command.executed']);
    }

    public function test_user_without_execute_permission_is_forbidden(): void
    {
        $viewer = User::factory()->create(['email' => 'viewer@test.ir']);
        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $viewer->getKey(),
            'role_id' => Role::query()->where('key', 'client-viewer')->valueOrFail('id'),
            'status' => 'active',
        ]);

        $this->actingAs($viewer)
            ->post("/app/commands/{$this->commandId}/dispatch")
            ->assertForbidden();

        $this->assertDatabaseHas('commands', ['id' => $this->commandId, 'status' => 'approved']);
    }

    public function test_connector_failure_marks_command_failed_and_redirects(): void
    {
        Http::fake(['https://wp.test/*' => Http::response('boom', 500)]);

        $this->actingAs($this->admin)
            ->post("/app/commands/{$this->commandId}/dispatch")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('commands', ['id' => $this->commandId, 'status' => 'failed']);
        $this->assertDatabaseHas('command_execution_logs', ['command_id' => $this->commandId, 'status' => 'failed']);
    }
}
