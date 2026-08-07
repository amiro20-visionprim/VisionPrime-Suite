<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Seo\Models\Recommendation;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecommendationTest extends TestCase
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
        $user = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);

        return [$organization, $user, $site];
    }

    public function test_user_can_create_recommendation_with_owner_and_due_date(): void
    {
        [$organization, $user, $site] = $this->setUpWorkspace();
        $owner = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role_id' => Role::query()->where('key', 'content-manager')->valueOrFail('id'), 'status' => 'active']);

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/recommendations', [
                'site_id' => $site->id,
                'title' => 'بازنویسی متا توضیح صفحه خدمات',
                'body' => 'توضیح کوتاه و واضح همراه با دعوت به اقدام بنویسید.',
                'priority' => 'high',
                'owner_id' => $owner->id,
                'due_at' => '2026-08-20',
            ])
            ->assertRedirect('/app/recommendations');

        $this->assertDatabaseHas('recommendations', [
            'site_id' => $site->id,
            'title' => 'بازنویسی متا توضیح صفحه خدمات',
            'priority' => 'high',
            'status' => 'active',
            'owner_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'recommendation.created']);
    }

    public function test_user_cannot_create_recommendation_for_another_organizations_site(): void
    {
        [$organization, $user] = $this->setUpWorkspace();
        $foreignOrg = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'F', 'slug' => 'f-'.Str::random(5), 'status' => 'active']);
        $foreignClient = Client::query()->create(['organization_id' => $foreignOrg->id, 'public_id' => (string) Str::ulid(), 'name' => 'FC', 'status' => 'active']);
        $foreignProject = Project::query()->create(['organization_id' => $foreignOrg->id, 'client_id' => $foreignClient->id, 'public_id' => (string) Str::ulid(), 'name' => 'FP', 'status' => 'active']);
        $foreignSite = Site::query()->create(['organization_id' => $foreignOrg->id, 'project_id' => $foreignProject->id, 'public_id' => (string) Str::ulid(), 'name' => 'FS', 'canonical_url' => 'https://foreign.ir', 'status' => 'active']);

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/recommendations', [
                'site_id' => $foreignSite->id,
                'title' => 'نفوذ',
                'body' => '',
                'priority' => 'low',
            ])
            ->assertNotFound();
    }

    public function test_opportunity_can_be_converted_to_recommendation(): void
    {
        [$organization, $user, $site] = $this->setUpWorkspace();

        $opportunityId = DB::table('opportunities')->insertGetId([
            'site_id' => $site->id,
            'type' => 'ctr_gap',
            'score' => 88.0,
            'confidence' => 0.9,
            'status' => 'open',
            'explanation' => 'شکاف نرخ کلیک قابل توجه',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/opportunities/{$opportunityId}/recommendation")
            ->assertRedirect('/app/recommendations');

        $this->assertDatabaseHas('recommendations', [
            'site_id' => $site->id,
            'source_type' => 'opportunity',
            'source_id' => $opportunityId,
            'priority' => 'high',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'recommendation.created_from_opportunity']);
    }

    public function test_user_can_update_recommendation_owner_status_and_due_date(): void
    {
        [$organization, $user, $site] = $this->setUpWorkspace();
        $owner = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $owner->id, 'role_id' => Role::query()->where('key', 'content-manager')->valueOrFail('id'), 'status' => 'active']);
        $recommendation = Recommendation::query()->create(['site_id' => $site->id, 'source_type' => 'manual', 'title' => 'قابل ویرایش', 'body' => '', 'priority' => 'low', 'status' => 'draft']);

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->put("/app/recommendations/{$recommendation->id}", [
                'owner_id' => $owner->id,
                'due_at' => '2026-08-25',
                'priority' => 'high',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('recommendations', [
            'id' => $recommendation->id,
            'owner_id' => $owner->id,
            'priority' => 'high',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'recommendation.updated']);
    }

    public function test_owner_must_be_active_member_of_the_organization(): void
    {
        [$organization, $user, $site] = $this->setUpWorkspace();
        $outsider = User::factory()->create();

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/recommendations', [
                'site_id' => $site->id,
                'title' => 'پیشنهاد',
                'body' => '',
                'priority' => 'medium',
                'owner_id' => $outsider->id,
            ])
            ->assertSessionHasErrors('owner_id');

        $this->assertDatabaseMissing('recommendations', ['title' => 'پیشنهاد']);
    }

    public function test_recommendations_index_lists_only_own_organizations_items(): void
    {
        [$organization, $user, $site] = $this->setUpWorkspace();
        Recommendation::query()->create(['site_id' => $site->id, 'source_type' => 'manual', 'title' => 'Own Recommendation', 'body' => '', 'priority' => 'medium', 'status' => 'active']);

        $foreignOrg = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'F', 'slug' => 'f-'.Str::random(5), 'status' => 'active']);
        $foreignClient = Client::query()->create(['organization_id' => $foreignOrg->id, 'public_id' => (string) Str::ulid(), 'name' => 'FC', 'status' => 'active']);
        $foreignProject = Project::query()->create(['organization_id' => $foreignOrg->id, 'client_id' => $foreignClient->id, 'public_id' => (string) Str::ulid(), 'name' => 'FP', 'status' => 'active']);
        $foreignSite = Site::query()->create(['organization_id' => $foreignOrg->id, 'project_id' => $foreignProject->id, 'public_id' => (string) Str::ulid(), 'name' => 'FS', 'canonical_url' => 'https://foreign.ir', 'status' => 'active']);
        Recommendation::query()->create(['site_id' => $foreignSite->id, 'source_type' => 'manual', 'title' => 'Foreign Recommendation', 'body' => '', 'priority' => 'low', 'status' => 'draft']);

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/recommendations')
            ->assertOk()
            ->assertSee('Own Recommendation')
            ->assertDontSee('Foreign Recommendation');
    }
}
