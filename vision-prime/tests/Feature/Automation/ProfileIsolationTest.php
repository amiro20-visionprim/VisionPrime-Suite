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

/**
 * ایزوله‌سازی پروفایل‌های اتوماسیون:
 * پروفایل‌های سفارشی فقط برای سازمان‌سازنده قابل استفاده‌اند؛ پروفایل‌های سیستمی عمومی‌اند.
 */
class ProfileIsolationTest extends TestCase
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

    /** @return array{id: int, org: Organization} */
    private function createCustomProfile(?Organization $org = null, string $slugSuffix = ''): array
    {
        $org = $org ?? Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'X', 'slug' => 'x-'.Str::random(5), 'status' => 'active']);
        $id = \DB::table('automation_profiles')->insertGetId([
            'name' => 'سفارشی', 'slug' => 'custom-'.Str::lower(Str::random(8)).$slugSuffix, 'kind' => 'custom', 'scope' => 'site',
            'organization_id' => $org->id, 'automation_level' => 3, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 90,
            'high_risk_threshold' => 95, 'risk_tier_max' => 'R3', 'enabled_content_types' => json_encode(['meta'], JSON_UNESCAPED_UNICODE),
            'daily_command_limit' => 25, 'daily_mutation_limit' => 10, 'rollback_hours' => 336, 'auto_rollback' => true,
            'alert_level' => 'alert', 'reviewer_policy' => 'one', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['id' => $id, 'org' => $org];
    }

    public function test_foreign_organizations_custom_profile_cannot_be_routed(): void
    {
        $foreign = $this->createCustomProfile();

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post("/app/sites/{$this->site->id}/automation/routes", ['routes' => [['content_type' => 'meta', 'profile_id' => $foreign['id']]]])
            ->assertStatus(422);

        $this->assertSame(0, \DB::table('site_profile_routes')->count());
    }

    public function test_foreign_organizations_custom_profile_cannot_be_copied(): void
    {
        $foreign = $this->createCustomProfile();

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post("/app/sites/{$this->site->id}/automation/profiles/copy", ['profile_id' => $foreign['id']])
            ->assertStatus(422);

        $this->assertSame(0, \DB::table('automation_profiles')->where('kind', 'custom')->where('organization_id', $this->organization->id)->count());
    }

    public function test_foreign_organizations_custom_profile_cannot_be_set_as_active(): void
    {
        $foreign = $this->createCustomProfile();

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->put("/app/sites/{$this->site->id}/automation", ['active_profile_id' => $foreign['id']])
            ->assertStatus(422);

        $this->assertDatabaseHas('site_automation_policies', ['site_id' => $this->site->id, 'active_profile_id' => null]);
    }

    public function test_system_profile_and_own_custom_profile_remain_usable(): void
    {
        $systemId = \DB::table('automation_profiles')->where('slug', 'safe-start')->value('id');
        $own = $this->createCustomProfile($this->organization, '-own');

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post("/app/sites/{$this->site->id}/automation/routes", ['routes' => [['content_type' => 'meta', 'profile_id' => $systemId]]])
            ->assertRedirect();
        $this->assertDatabaseHas('site_profile_routes', ['site_id' => $this->site->id, 'content_type' => 'meta', 'profile_id' => $systemId]);

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post("/app/sites/{$this->site->id}/automation/profiles/copy", ['profile_id' => $systemId])
            ->assertRedirect();
        $copies = \DB::table('automation_profiles')->where('kind', 'custom')->where('organization_id', $this->organization->id)->get();
        $this->assertSame(2, $copies->count(), 'own profile + copied profile should both belong to this org');
        $this->assertTrue($copies->contains('id', $own['id']));
        $this->assertTrue($copies->contains(fn (object $p): bool => str_starts_with((string) $p->slug, 'safe-start-copy-')));
    }
}
