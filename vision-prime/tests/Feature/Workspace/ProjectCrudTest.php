<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_update_and_archive_project(): void
    {
        $org = $this->org();
        $admin = $this->member($org, 'agency-admin');
        $client = $this->client($org);
        $this->actingAs($admin)->post('/app/projects', ['client_id' => $client->id, 'name' => 'رشد ارگانیک', 'objective' => 'افزایش لید'])->assertRedirect();
        $project = Project::query()->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.created', 'subject_id' => $project->id]);
        $this->actingAs($admin)->put("/app/projects/{$project->id}", ['client_id' => $client->id, 'name' => 'رشد ارگانیک جدید', 'objective' => 'هدف جدید'])->assertRedirect();
        $this->actingAs($admin)->delete("/app/projects/{$project->id}")->assertRedirect('/app/projects');
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.archived', 'subject_id' => $project->id]);
    }

    private function org(): Organization
    {
        return Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'آژانس', 'slug' => 'agency-'.Str::lower(Str::random(6)), 'status' => 'active']);
    }

    private function member(Organization $o, string $role): User
    {
        $u = User::factory()->create();
        Membership::query()->create(['organization_id' => $o->id, 'user_id' => $u->id, 'role_id' => Role::query()->where('key', $role)->valueOrFail('id'), 'status' => 'active']);

        return $u;
    }

    private function client(Organization $o): Client
    {
        return Client::query()->create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'مشتری', 'status' => 'active']);
    }
}
