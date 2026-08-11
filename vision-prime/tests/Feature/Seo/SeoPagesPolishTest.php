<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeoPagesPolishTest extends TestCase
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
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);

        $profileId = DB::table('url_profiles')->insertGetId([
            'site_id' => $site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://e.ir/shop/item/',
            'content_type' => 'product',
            'post_status' => 'publish',
            'metadata' => json_encode(['gsc' => ['clicks' => 10, 'impressions' => 500, 'ctr' => 0.02, 'position' => 14]], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditId = DB::table('money_page_audits')->insertGetId([
            'url_profile_id' => $profileId,
            'score' => 65.0,
            'summary' => json_encode(['issues' => 2]),
            'audited_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('money_page_issues')->insert([
            ['money_page_audit_id' => $auditId, 'key' => 'low_ctr', 'severity' => 'medium', 'explanation' => 'نرخ کلیک پایین', 'created_at' => now(), 'updated_at' => now()],
            ['money_page_audit_id' => $auditId, 'key' => 'weak_visibility', 'severity' => 'high', 'explanation' => 'جایگاه ضعیف', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('conversion_risks')->insertGetId([
            'url_profile_id' => $profileId,
            'key' => 'thin_content',
            'severity' => 'high',
            'score' => 78.0,
            'explanation' => 'محتوای کوتاه',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('opportunities')->insertGetId([
            'site_id' => $site->id,
            'url_profile_id' => $profileId,
            'type' => 'ctr_gap',
            'score' => 70.0,
            'confidence' => 0.7,
            'status' => 'open',
            'explanation' => 'شکاف CTR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$organization, $admin, $site, $profileId, $auditId];
    }

    public function test_money_pages_index_lists_audits_with_issue_counts(): void
    {
        [$organization, $admin, , , $auditId] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/money-pages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/MoneyPages/Index')
                ->has('audits.data', 1)
                ->where('audits.data.0.id', $auditId)
                ->where('audits.data.0.issues_count', 2));
    }

    public function test_money_page_show_renders_issues_gsc_and_opportunities(): void
    {
        [$organization, $admin, , , $auditId] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->get("/app/money-pages/{$auditId}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/MoneyPages/Show')
                ->where('audit.id', $auditId)
                ->where('audit.score', 65)
                ->has('audit.issues', 2)
                ->where('audit.gsc.clicks', 10)
                ->has('audit.opportunities', 1));
    }

    public function test_money_page_of_another_organization_is_404(): void
    {
        [$organization, $admin] = $this->setUpWorkspace();

        $foreignOrg = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'F', 'slug' => 'f-'.Str::random(5), 'status' => 'active']);
        $foreignClient = Client::query()->create(['organization_id' => $foreignOrg->id, 'public_id' => (string) Str::ulid(), 'name' => 'FC', 'status' => 'active']);
        $foreignProject = Project::query()->create(['organization_id' => $foreignOrg->id, 'client_id' => $foreignClient->id, 'public_id' => (string) Str::ulid(), 'name' => 'FP', 'status' => 'active']);
        $foreignSite = Site::query()->create(['organization_id' => $foreignOrg->id, 'project_id' => $foreignProject->id, 'public_id' => (string) Str::ulid(), 'name' => 'FS', 'canonical_url' => 'https://f.ir', 'status' => 'active']);
        $foreignProfileId = DB::table('url_profiles')->insertGetId([
            'site_id' => $foreignSite->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://f.ir/x/',
            'content_type' => 'page',
            'post_status' => 'publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignAuditId = DB::table('money_page_audits')->insertGetId([
            'url_profile_id' => $foreignProfileId,
            'score' => 50.0,
            'summary' => '{}',
            'audited_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->get("/app/money-pages/{$foreignAuditId}")
            ->assertNotFound();
    }

    public function test_conversion_risks_support_severity_filter(): void
    {
        [$organization, $admin] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/conversion-risks?severity=high')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/ConversionRisks/Index')
                ->has('risks.data', 1)
                ->where('filters.severity', 'high'));

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/conversion-risks?severity=low')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/ConversionRisks/Index')
                ->has('risks.data', 0));
    }

    public function test_url_profiles_include_gsc_metrics_and_audit_link(): void
    {
        [$organization, $admin, , $profileId, $auditId] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/url-profiles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/UrlProfiles/Index')
                ->has('profiles.data', 1)
                ->where('profiles.data.0.id', $profileId)
                ->where('profiles.data.0.type', 'product')
                ->where('profiles.data.0.status', 'publish')
                ->where('profiles.data.0.gsc.clicks', 10)
                ->where('profiles.data.0.auditId', $auditId));
    }

    public function test_gsc_metrics_support_site_and_date_filters(): void
    {
        [$organization, $admin, $site] = $this->setUpWorkspace();

        $accountId = DB::table('gsc_accounts')->insertGetId([
            'organization_id' => $organization->id,
            'google_subject' => 'sub-'.Str::random(8),
            'email' => 'gsc@test',
            'token_ciphertext' => 'x',
            'status' => 'connected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $propertyId = DB::table('gsc_properties')->insertGetId([
            'site_id' => $site->id,
            'gsc_account_id' => $accountId,
            'property_uri' => 'https://e.ir/',
            'property_type' => 'site',
            'status' => 'selected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('gsc_query_metrics')->insert([
            ['gsc_property_id' => $propertyId, 'date' => '2026-07-05', 'query' => 'red dress', 'clicks' => 5, 'impressions' => 100, 'ctr' => 0.05, 'position' => 8.5],
            ['gsc_property_id' => $propertyId, 'date' => '2026-08-01', 'query' => 'buy guide', 'clicks' => 2, 'impressions' => 60, 'ctr' => 0.033, 'position' => 12.0],
        ]);
        DB::table('gsc_page_metrics')->insert([
            ['gsc_property_id' => $propertyId, 'date' => '2026-07-05', 'page_url' => 'https://e.ir/shop/item/', 'clicks' => 5, 'impressions' => 100, 'ctr' => 0.05, 'position' => 8.5],
            ['gsc_property_id' => $propertyId, 'date' => '2026-08-01', 'page_url' => 'https://e.ir/shop/item/', 'clicks' => 2, 'impressions' => 60, 'ctr' => 0.033, 'position' => 12.0],
        ]);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/gsc/queries?site_id='.$site->id.'&date_from=2026-07-01&date_to=2026-07-31')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Gsc/Queries')
                ->has('metrics.data', 1)
                ->where('metrics.data.0.query', 'red dress')
                ->where('filters.site_id', (string) $site->id)
                ->where('filters.date_from', '2026-07-01')
                ->where('filters.date_to', '2026-07-31'));

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/gsc/pages?date_from=2026-08-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Gsc/Pages')
                ->has('metrics.data', 1)
                ->where('metrics.data.0.date', '2026-08-01'));
    }
}
