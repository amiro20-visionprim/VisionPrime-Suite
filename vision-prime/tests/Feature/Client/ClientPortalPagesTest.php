<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\ClientUserAssignment;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientPortalPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $organization = Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'سازمان آزمون',
            'slug' => 'organization-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        $this->user = User::factory()->create();
        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $this->user->getKey(),
            'role_id' => Role::query()->where('key', 'client-viewer')->valueOrFail('id'),
            'status' => 'active',
        ]);

        $this->client = Client::query()->create([
            'organization_id' => $organization->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'مشتری آزمون',
            'status' => 'active',
        ]);

        ClientUserAssignment::query()->create([
            'client_id' => $this->client->getKey(),
            'user_id' => $this->user->getKey(),
            'portal_role' => 'viewer',
        ]);

        $project = Project::query()->create([
            'organization_id' => $organization->getKey(),
            'client_id' => $this->client->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'پروژه آزمون',
            'status' => 'active',
        ]);

        $this->site = Site::query()->create([
            'organization_id' => $organization->getKey(),
            'project_id' => $project->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'سایت آزمون',
            'canonical_url' => 'https://test.example.ir',
            'status' => 'active',
        ]);
    }

    public function test_site_health_page_shows_real_site_data(): void
    {
        $this->actingAs($this->user)->get('/client/site-health')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/SiteHealth')
                ->where('summary.total_sites', 1)
                ->has('sites', 1)
                ->where('sites.0.name', 'سایت آزمون')
                ->where('sites.0.connected', false));
    }

    public function test_priorities_page_lists_open_opportunities_and_active_recommendations(): void
    {
        $profileId = $this->createUrlProfile();

        \DB::table('opportunities')->insert([
            'site_id' => $this->site->getKey(),
            'url_profile_id' => $profileId,
            'type' => 'conversion_boost',
            'score' => 86,
            'confidence' => 0.82,
            'status' => 'open',
            'explanation' => 'بهبود CTA صفحه می‌تواند تبدیل را افزایش دهد.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('recommendations')->insert([
            'site_id' => $this->site->getKey(),
            'source_type' => 'opportunity',
            'source_id' => null,
            'title' => 'بازنویسی صفحه خدمات',
            'body' => 'تقویت دعوت به اقدام.',
            'priority' => 'high',
            'status' => 'active',
            'owner_id' => null,
            'due_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user)->get('/client/opportunities')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Priorities')
                ->has('opportunities', 1)
                ->where('opportunities.0.type', 'conversion_boost')
                ->has('recommendations', 1)
                ->where('recommendations.0.priority', 'high'));
    }

    public function test_decisions_page_lists_pending_approvals_and_reviews(): void
    {
        \DB::table('commands')->insert([
            'site_id' => $this->site->getKey(),
            'source_type' => 'recommendation',
            'source_id' => null,
            'type' => 'update_meta_title',
            'risk_tier' => 'R2',
            'payload' => json_encode(['title' => 'عنوان جدید']),
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'pending_approval',
            'expires_at' => now()->addDays(7),
            'policy_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profileId = $this->createUrlProfile();
        \DB::table('review_items')->insert([
            'site_id' => $this->site->getKey(),
            'subject_type' => 'url_profile',
            'subject_id' => $profileId,
            'status' => 'pending_review',
            'due_at' => now()->addDays(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user)->get('/client/decisions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Decisions')
                ->has('commands', 1)
                ->where('commands.0.type', 'update_meta_title')
                ->has('reviews', 1)
                ->where('reviews.0.subject_type', 'url_profile'));
    }

    public function test_activity_page_lists_organization_audit_logs(): void
    {
        $organizationId = $this->client->organization_id;

        \DB::table('audit_logs')->insert([
            'organization_id' => $organizationId,
            'actor_id' => $this->user->getKey(),
            'action' => 'site.created',
            'subject_type' => 'site',
            'subject_id' => $this->site->getKey(),
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->user)->get('/client/activity')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Activity')
                ->has('activities', 1)
                ->where('activities.0.action', 'site.created')
                ->where('activities.0.label', 'سایت جدید به پروژه اضافه شد'));
    }

    public function test_client_dashboard_includes_real_priorities_report_and_activities(): void
    {
        $profileId = $this->createUrlProfile();

        \DB::table('opportunities')->insert([
            'site_id' => $this->site->getKey(),
            'url_profile_id' => $profileId,
            'type' => 'ctr_gap',
            'score' => 91,
            'confidence' => 0.78,
            'status' => 'open',
            'explanation' => 'بهبود عنوان متا می‌تواند نرخ کلیک را افزایش دهد.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reportId = \DB::table('reports')->insertGetId([
            'site_id' => $this->site->getKey(),
            'type' => 'گزارش دوره',
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'status' => 'published',
            'content' => json_encode(['opportunities' => 1, 'high_risks' => 2, 'recommendations' => 3]),
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('audit_logs')->insert([
            'organization_id' => $this->client->organization_id,
            'actor_id' => $this->user->getKey(),
            'action' => 'site.created',
            'subject_type' => 'site',
            'subject_id' => $this->site->getKey(),
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->user)->get('/client/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Client/Dashboard')
                ->has('opportunities', 1)
                ->where('opportunities.0.score', 91)
                ->where('latestReport.id', $reportId)
                ->has('recentActivities', 1)
                ->where('recentActivities.0.label', 'سایت جدید به پروژه اضافه شد'));
    }

    private function createUrlProfile(): int
    {
        return \DB::table('url_profiles')->insertGetId([
            'site_id' => $this->site->getKey(),
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://test.example.ir/services/seo',
            'slug' => 'services/seo',
            'content_type' => 'page',
            'post_status' => 'publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
