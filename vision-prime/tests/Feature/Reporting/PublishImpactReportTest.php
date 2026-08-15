<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Domains\Organization\Models\Organization;
use App\Domains\Reporting\Actions\BuildContentImpactSummary;
use App\Domains\Reporting\Actions\BuildPublishImpactReport;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublishImpactReportTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private int $propertyId;

    protected function setUp(): void
    {
        parent::setUp();
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $this->site = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        \DB::table('site_connections')->insert(['site_id' => $this->site->id, 'status' => 'connected', 'platform_url' => 'https://wp.test', 'secret_ciphertext' => Crypt::encryptString('secret'), 'created_at' => now(), 'updated_at' => now()]);
        $gscAccountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $o->id, 'google_subject' => 'acct:'.Str::random(8), 'email' => 'gsc@test.local', 'token_ciphertext' => Crypt::encryptString('token'), 'token_expires_at' => now()->addDay(), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $this->propertyId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $gscAccountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'site', 'status' => 'selected', 'selected_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function command(array $overrides = []): object
    {
        $row = array_merge([
            'site_id' => $this->site->id,
            'source_type' => 'ai_generation',
            'type' => 'publish_new_article',
            'content_type' => 'article',
            'risk_tier' => 'R3',
            'payload' => json_encode(['slug' => 'seo-guide'], JSON_UNESCAPED_UNICODE),
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'executed',
            'confidence_score' => 85,
            'decision_source' => 'policy',
            'published_at' => now()->subDays(10),
            'expires_at' => now()->addDay(),
            'policy_version' => 1,
            'created_at' => now()->subDays(15),
            'updated_at' => now(),
        ], $overrides);
        $id = \DB::table('commands')->insertGetId($row);

        return \DB::table('commands')->where('id', $id)->first();
    }

    private function metrics(string $url, string $date, int $clicks, float $position): void
    {
        \DB::table('gsc_page_metrics')->insert([
            'gsc_property_id' => $this->propertyId,
            'date' => $date,
            'page_url' => $url,
            'clicks' => $clicks,
            'impressions' => 500,
            'ctr' => 0.02,
            'position' => $position,
        ]);
    }

    public function test_ready_report_compares_before_and_after_windows(): void
    {
        $command = $this->command();

        // قبل از انتشار (پنجرهٔ ۱۴ روز قبل، تا یک روز پیش از انتشار) — جایگاه ضعیف، کلیک کم
        $this->metrics('https://wp.test/seo-guide/', now()->subDays(12)->toDateString(), 2, 18.5);
        $this->metrics('https://wp.test/seo-guide/', now()->subDays(11)->toDateString(), 3, 17.0);
        // بعد از انتشار — جایگاه بهتر، کلیک بیشتر
        $this->metrics('https://wp.test/seo-guide/', now()->subDays(8)->toDateString(), 8, 9.0);
        $this->metrics('https://wp.test/seo-guide/', now()->subDays(6)->toDateString(), 12, 7.5);

        $report = app(BuildPublishImpactReport::class)->handle($command);

        $this->assertSame('ready', $report['status']);
        $this->assertSame('improved', $report['verdict']);
        $this->assertSame('https://wp.test/seo-guide/', $report['url']);
        $this->assertSame(2, $report['before']['days']);
        $this->assertSame(2, $report['after']['days']);
        $this->assertSame(5, $report['before']['clicks']);
        $this->assertSame(20, $report['after']['clicks']);
        $this->assertSame(17.75, $report['before']['avg_position']);
        $this->assertSame(8.25, $report['after']['avg_position']);
        $this->assertSame(-9.5, $report['delta']['position']);
        $this->assertSame(15, $report['delta']['clicks']);
        $this->assertCount(29, $report['series']); // ۱۴ قبل + روز انتشار + ۱۴ بعد
        // سری از published−14 (= now−24) شروع می‌شود: now−12 → index 12، now−6 → index 18
        $this->assertSame(2, $report['series'][12]['clicks']);
        $this->assertSame(18.5, $report['series'][12]['position']);
        $this->assertSame(12, $report['series'][18]['clicks']);
        $this->assertSame(8, $report['series'][16]['clicks']);
        $this->assertNull($report['series'][0]['position']);
        $this->assertSame(0, $report['series'][0]['clicks']);
    }

    public function test_declined_verdict_when_position_worsens(): void
    {
        $command = $this->command();

        $this->metrics('https://wp.test/seo-guide/', now()->subDays(12)->toDateString(), 5, 5.0);
        $this->metrics('https://wp.test/seo-guide/', now()->subDays(4)->toDateString(), 2, 22.0);

        $report = app(BuildPublishImpactReport::class)->handle($command);

        $this->assertSame('ready', $report['status']);
        $this->assertSame('declined', $report['verdict']);
        $this->assertSame(17.0, $report['delta']['position']);
    }

    public function test_insufficient_data_when_no_metrics_after_publish(): void
    {
        $command = $this->command();

        $this->metrics('https://wp.test/seo-guide/', now()->subDays(12)->toDateString(), 5, 5.0);

        $report = app(BuildPublishImpactReport::class)->handle($command);

        $this->assertSame('insufficient_data', $report['status']);
        $this->assertSame('no_observed', $report['reason']);
    }

    public function test_non_publish_command_is_not_applicable(): void
    {
        $command = $this->command(['type' => 'update_meta_title']);

        $report = app(BuildPublishImpactReport::class)->handle($command);

        $this->assertSame('not_applicable', $report['status']);
    }

    public function test_summary_aggregates_impact_and_picks_best_worst(): void
    {
        // بهترین: کلیک +۱۵
        $this->command(['idempotency_key' => (string) Str::uuid(), 'published_at' => now()->subDays(10)]);
        $this->metrics('https://wp.test/seo-guide/', now()->subDays(12)->toDateString(), 2, 18.5);
        $this->metrics('https://wp.test/seo-guide/', now()->subDays(8)->toDateString(), 8, 9.0);
        $this->metrics('https://wp.test/seo-guide/', now()->subDays(6)->toDateString(), 9, 7.5);

        // ضعیف‌ترین: افت
        $this->command([
            'idempotency_key' => (string) Str::uuid(),
            'payload' => json_encode(['slug' => 'bad-page'], JSON_UNESCAPED_UNICODE),
            'published_at' => now()->subDays(8),
        ]);
        $this->metrics('https://wp.test/bad-page/', now()->subDays(10)->toDateString(), 6, 6.0);
        $this->metrics('https://wp.test/bad-page/', now()->subDays(4)->toDateString(), 2, 20.0);

        // بدون دادهٔ GSC → نباید شمرده شود
        $this->command(['idempotency_key' => (string) Str::uuid(), 'payload' => json_encode(['slug' => 'no-data-page'], JSON_UNESCAPED_UNICODE)]);

        $summary = app(BuildContentImpactSummary::class)->handle([$this->site->id]);

        $this->assertSame(2, $summary['reported']);
        $this->assertSame(1, $summary['insufficient_data']);
        $this->assertSame(1, $summary['verdicts']['improved']);
        $this->assertSame(1, $summary['verdicts']['declined']);
        $this->assertSame('https://wp.test/seo-guide/', $summary['best']['url']);
        $this->assertSame('https://wp.test/bad-page/', $summary['worst']['url']);
        $this->assertSame(15, $summary['best']['delta']['clicks']);
        $this->assertSame(-4, $summary['worst']['delta']['clicks']);
        // هشدار افت: فقط مورد declined در declines می‌آید
        $this->assertCount(1, $summary['declines']);
        $this->assertSame('https://wp.test/bad-page/', $summary['declines'][0]['url']);
        $this->assertSame(-4, $summary['declines'][0]['delta']['clicks']);
    }

    public function test_summary_empty_when_nothing_executed(): void
    {
        $summary = app(BuildContentImpactSummary::class)->handle([$this->site->id]);

        $this->assertSame(0, $summary['reported']);
        $this->assertSame(0, $summary['verdicts']['improved']);
        $this->assertNull($summary['best']);
        $this->assertNull($summary['worst']);
    }
}
