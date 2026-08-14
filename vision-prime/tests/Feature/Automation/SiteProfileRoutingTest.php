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
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteProfileRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->organization = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o-'.Str::random(5), 'status' => 'active']);
        $this->user = User::factory()->create();
        Membership::query()->create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $this->organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $this->organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $this->site = Site::query()->create(['organization_id' => $this->organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        \DB::table('site_automation_policies')->insert(['site_id' => $this->site->id, 'level' => 2, 'rules' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_update_routes_replaces_content_type_mapping(): void
    {
        $profileId = \DB::table('automation_profiles')->insertGetId([
            'name' => 'مقالات', 'slug' => 'articles', 'kind' => 'custom', 'scope' => 'site', 'automation_level' => 3,
            'ai_policy' => 'bounded_auto', 'confidence_threshold' => 90, 'high_risk_threshold' => 95, 'risk_tier_max' => 'R3',
            'enabled_content_types' => json_encode(['article'], JSON_UNESCAPED_UNICODE), 'daily_command_limit' => 25,
            'daily_mutation_limit' => 10, 'rollback_hours' => 336, 'auto_rollback' => true, 'alert_level' => 'alert',
            'reviewer_policy' => 'one', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post("/app/sites/{$this->site->id}/automation/routes", ['routes' => [['content_type' => 'article', 'profile_id' => $profileId]]])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('site_profile_routes', ['site_id' => $this->site->id, 'content_type' => 'article', 'profile_id' => $profileId]);

        // جایگزینی کامل
        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post("/app/sites/{$this->site->id}/automation/routes", ['routes' => [['content_type' => 'meta', 'profile_id' => $profileId]]])
            ->assertRedirect();

        $this->assertDatabaseMissing('site_profile_routes', ['site_id' => $this->site->id, 'content_type' => 'article']);
        $this->assertDatabaseHas('site_profile_routes', ['site_id' => $this->site->id, 'content_type' => 'meta', 'profile_id' => $profileId]);
    }

    public function test_copy_profile_creates_custom_copy(): void
    {
        $systemProfileId = \DB::table('automation_profiles')->insertGetId([
            'name' => 'شروع امن', 'slug' => 'tpl-safe-start', 'kind' => 'system', 'scope' => 'org', 'automation_level' => 1,
            'ai_policy' => 'draft_only', 'confidence_threshold' => 80, 'high_risk_threshold' => 90, 'risk_tier_max' => 'R1',
            'enabled_content_types' => json_encode(['meta'], JSON_UNESCAPED_UNICODE), 'daily_command_limit' => 5,
            'daily_mutation_limit' => 2, 'rollback_hours' => 168, 'auto_rollback' => false, 'alert_level' => 'warn',
            'reviewer_policy' => 'one', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post("/app/sites/{$this->site->id}/automation/profiles/copy", ['profile_id' => $systemProfileId])
            ->assertRedirect()
            ->assertSessionHas('status');

        $copy = \DB::table('automation_profiles')->where('kind', 'custom')->where('slug', 'like', 'tpl-safe-start-copy-%')->first();
        $this->assertNotNull($copy);
        $this->assertSame(1, (int) $copy->automation_level);
        $this->assertSame('draft_only', $copy->ai_policy);
    }
}
