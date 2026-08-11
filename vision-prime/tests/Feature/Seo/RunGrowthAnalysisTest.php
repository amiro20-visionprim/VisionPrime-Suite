<?php

declare(strict_types=1);

namespace Tests\Feature\Seo;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Seo\Actions\RunGrowthAnalysis;
use App\Domains\Seo\Jobs\RunGrowthAnalysisJob;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class RunGrowthAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function setUpSiteWithGscData(): array
    {
        $organization = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'R', 'slug' => 'r-'.Str::random(5), 'status' => 'active']);
        $user = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);

        $account = \DB::table('gsc_accounts')->insertGetId([
            'organization_id' => $organization->id,
            'google_subject' => 'sub-'.Str::random(8),
            'email' => 'gsc@test',
            'token_ciphertext' => 'x',
            'status' => 'connected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $property = \DB::table('gsc_properties')->insertGetId([
            'site_id' => $site->id,
            'gsc_account_id' => $account,
            'property_uri' => 'https://e.ir/',
            'property_type' => 'site',
            'status' => 'selected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Two real pages: one money page (weak signals), one blog guide.
        $moneyUrl = 'https://e.ir/shop/red-dress/';
        $guideUrl = 'https://e.ir/blog/buy-guide';
        foreach ([
            [$moneyUrl, 5, 500, 0.012, 25],
            [$guideUrl, 5, 300, 0.0167, 6],
        ] as [$url, $clicks, $impressions, $ctr, $position]) {
            \DB::table('gsc_page_metrics')->insert([
                'gsc_property_id' => $property,
                'date' => '2026-01-10',
                'page_url' => $url,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $ctr,
                'position' => $position,
            ]);
        }

        foreach ([
            ['red dress', $moneyUrl, 5, 500, 0.012, 25],
            ['red dress', 'https://e.ir/shop/red-dress-alt/', 3, 200, 0.015, 12],
            ['buy guide', $guideUrl, 5, 300, 0.0167, 6],
            ['best seo tool', $guideUrl, 15, 100, 0.15, 7],
        ] as [$query, $url, $clicks, $impressions, $ctr, $position]) {
            \DB::table('gsc_query_page_metrics')->insert([
                'gsc_property_id' => $property,
                'date' => '2026-01-10',
                'query' => $query,
                'page_url' => $url,
                'clicks' => $clicks,
                'impressions' => $impressions,
                'ctr' => $ctr,
                'position' => $position,
            ]);
        }

        // A synced content snapshot for the money page (thin content risk).
        $profileId = \DB::table('url_profiles')->insertGetId([
            'site_id' => $site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => $moneyUrl,
            'content_type' => 'product',
            'post_status' => 'publish',
            'metadata' => json_encode(['meta_title' => 'Red Dress']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('content_snapshots')->insert([
            'url_profile_id' => $profileId,
            'content_hash' => 'h',
            'content' => 'خرید سریع',
            'word_count' => 2,
            'captured_at' => now(),
        ]);

        return [$organization, $user, $site];
    }

    public function test_pipeline_derives_intelligence_from_gsc_data(): void
    {
        [, , $site] = $this->setUpSiteWithGscData();

        $result = app(RunGrowthAnalysis::class)->handle($site);

        // 2 pages -> 2 url profiles (money page already existed and got updated, guide created)
        $this->assertSame(2, $result['url_profiles']);
        $this->assertDatabaseCount('url_profiles', 2);
        $this->assertDatabaseHas('url_profiles', ['site_id' => $site->id, 'canonical_url' => 'https://e.ir/shop/red-dress/', 'content_type' => 'product']);
        $this->assertDatabaseHas('url_profiles', ['site_id' => $site->id, 'canonical_url' => 'https://e.ir/blog/buy-guide', 'content_type' => 'post']);

        // 4 query-page rows processed, 3 distinct queries mapped
        $this->assertSame(4, $result['keyword_insights']);
        $this->assertSame(3, \DB::table('keyword_insights')->where('site_id', $site->id)->count());
        $this->assertDatabaseHas('keyword_insights', ['site_id' => $site->id, 'query_normalized' => 'red dress']);
        $this->assertDatabaseHas('intent_classifications', ['intent' => 'transactional']);

        // cannibalization + revenue + signal opportunities
        $this->assertSame(1, $result['cannibalization']);
        $this->assertSame(3, $result['revenue_opportunities']);
        $this->assertSame(4, $result['signal_opportunities']);
        $this->assertDatabaseHas('opportunities', ['site_id' => $site->id, 'type' => 'cannibalization']);
        $this->assertDatabaseHas('opportunities', ['site_id' => $site->id, 'type' => 'revenue_opportunity']);
        $this->assertDatabaseHas('opportunities', ['site_id' => $site->id, 'type' => 'ctr_gap']);
        $this->assertDatabaseHas('opportunities', ['site_id' => $site->id, 'type' => 'keyword_opportunity']);
        $this->assertDatabaseHas('opportunities', ['site_id' => $site->id, 'type' => 'conversion_boost']);

        // money page audit scored from real GSC signals (weak position + low CTR)
        $this->assertSame(1, $result['money_page_audits']);
        $audit = \DB::table('money_page_audits')->first();
        $this->assertLessThan(70, (int) $audit->score);
        $this->assertDatabaseHas('money_page_issues', ['key' => 'low_visibility']);
        $this->assertDatabaseHas('money_page_issues', ['key' => 'low_ctr']);

        // thin content risk -> recommendation
        $this->assertSame(1, $result['conversion_risks']);
        $this->assertDatabaseHas('conversion_risks', ['key' => 'thin_content', 'severity' => 'high']);
        $this->assertSame(1, $result['recommendations']);
        $this->assertDatabaseHas('recommendations', ['site_id' => $site->id, 'source_type' => 'conversion_risk']);

        // flagged money page audit -> review item (review queue is fed by analysis)
        $this->assertSame(1, $result['review_items']);
        $this->assertDatabaseHas('review_items', ['site_id' => $site->id, 'subject_type' => 'money_page_audit', 'status' => 'pending_review']);

        // idempotent: a second run must not duplicate rows
        app(RunGrowthAnalysis::class)->handle($site);
        $this->assertSame(2, \DB::table('url_profiles')->where('site_id', $site->id)->count());
        $this->assertSame(3, \DB::table('keyword_insights')->where('site_id', $site->id)->count());
        $this->assertSame(1, \DB::table('money_page_audits')->count());
        $this->assertSame(1, \DB::table('conversion_risks')->count());
        $this->assertSame(1, \DB::table('review_items')->where('site_id', $site->id)->count());
    }

    public function test_analyze_route_dispatches_analysis_job_for_own_property(): void
    {
        Queue::fake();
        [$organization, $user, $site] = $this->setUpSiteWithGscData();
        $property = \DB::table('gsc_properties')->where('site_id', $site->id)->first();

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/gsc/analyze', ['gsc_property_id' => $property->id])
            ->assertRedirect();

        Queue::assertPushed(RunGrowthAnalysisJob::class, fn (RunGrowthAnalysisJob $job) => $job->siteId === $site->id);
    }
}
