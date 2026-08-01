<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_create_normalize_and_archive_site(): void
    {
        $o = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'A', 'slug' => 'a-'.Str::random(4), 'status' => 'active']);
        $u = User::factory()->create();
        Membership::query()->create(['organization_id' => $o->id, 'user_id' => $u->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $c = Client::query()->create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::query()->create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $this->actingAs($u)->post('/app/sites', ['project_id' => $p->id, 'name' => 'Site', 'canonical_url' => 'HTTPS://Example.IR/services/', 'locale' => 'fa', 'timezone' => 'Asia/Tehran', 'business_importance' => 5])->assertRedirect();
        $s = Site::firstOrFail();
        $this->assertSame('https://example.ir/services', $s->canonical_url);
        $this->assertDatabaseHas('audit_logs', ['action' => 'site.created', 'subject_id' => $s->id]);
        $this->actingAs($u)->delete("/app/sites/{$s->id}")->assertRedirect('/app/sites');
        $this->assertSoftDeleted('sites', ['id' => $s->id]);
    }

    public function test_duplicate_canonical_url_in_same_organization_is_rejected(): void
    {
        $organization = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'A', 'slug' => 'duplicate-'.Str::random(4), 'status' => 'active']);
        $admin = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'Existing', 'canonical_url' => 'https://example.ir', 'status' => 'active']);

        $this->actingAs($admin)->post('/app/sites', [
            'project_id' => $project->id,
            'name' => 'Duplicate',
            'canonical_url' => 'https://EXAMPLE.ir/',
            'locale' => 'fa',
            'timezone' => 'Asia/Tehran',
            'business_importance' => 3,
        ])->assertSessionHasErrors('canonical_url');
    }

    public function test_user_cannot_archive_site_from_another_organization(): void
    {
        $organizationA = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'A', 'slug' => 'a-'.Str::random(5), 'status' => 'active']);
        $organizationB = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'B', 'slug' => 'b-'.Str::random(5), 'status' => 'active']);
        $adminA = User::factory()->create();
        Membership::query()->create(['organization_id' => $organizationA->id, 'user_id' => $adminA->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $clientB = Client::query()->create(['organization_id' => $organizationB->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $projectB = Project::query()->create(['organization_id' => $organizationB->id, 'client_id' => $clientB->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $siteB = Site::query()->create(['organization_id' => $organizationB->id, 'project_id' => $projectB->id, 'public_id' => (string) Str::ulid(), 'name' => 'Protected', 'canonical_url' => 'https://protected.example.ir', 'status' => 'active']);

        $this->actingAs($adminA)->delete("/app/sites/{$siteB->id}")->assertForbidden();
        $this->assertDatabaseHas('sites', ['id' => $siteB->id, 'deleted_at' => null]);
    }

    public function test_site_update_records_audit_and_rejects_duplicate_url(): void
    {
        $organization = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'U', 'slug' => 'u-'.Str::random(5), 'status' => 'active']);
        $admin = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'Old', 'canonical_url' => 'https://old.example.ir', 'status' => 'active']);
        Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'Taken', 'canonical_url' => 'https://taken.example.ir', 'status' => 'active']);

        $this->actingAs($admin)->put("/app/sites/{$site->id}", ['project_id' => $project->id, 'name' => 'New', 'canonical_url' => 'https://NEW.example.ir/', 'locale' => 'fa', 'timezone' => 'Asia/Tehran', 'business_importance' => 5])->assertRedirect();
        $this->assertDatabaseHas('sites', ['id' => $site->id, 'name' => 'New', 'canonical_url' => 'https://new.example.ir']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'site.updated', 'subject_id' => $site->id]);
        $this->actingAs($admin)->put("/app/sites/{$site->id}", ['project_id' => $project->id, 'name' => 'New', 'canonical_url' => 'https://taken.example.ir', 'locale' => 'fa', 'timezone' => 'Asia/Tehran', 'business_importance' => 5])->assertSessionHasErrors('canonical_url');
    }
}
