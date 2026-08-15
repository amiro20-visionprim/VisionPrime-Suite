<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Automation\Actions\CreateRollbackSnapshot;
use App\Domains\Automation\Actions\RecordMetricDropAlert;
use App\Domains\Automation\Actions\RollbackCommand;
use App\Domains\Automation\Jobs\RollbackMonitor;
use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use App\Notifications\AutomationAlert;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class RollbackCommandTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private int $profileId;

    protected function setUp(): void
    {
        parent::setUp();
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $this->site = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        \DB::table('site_connections')->insert(['site_id' => $this->site->id, 'status' => 'connected', 'platform_url' => 'https://wp.test', 'secret_ciphertext' => Crypt::encryptString('secret'), 'created_at' => now(), 'updated_at' => now()]);
        $this->profileId = \DB::table('automation_profiles')->insertGetId([
            'name' => 'تست رولبک', 'slug' => 'test-rollback', 'kind' => 'custom', 'scope' => 'site',
            'automation_level' => 3, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80,
            'high_risk_threshold' => 90, 'risk_tier_max' => 'R3', 'auto_rollback' => true,
            'enabled_content_types' => json_encode(['meta'], JSON_UNESCAPED_UNICODE),
            'daily_command_limit' => 25, 'daily_mutation_limit' => 10, 'rollback_hours' => 336,
            'alert_level' => 'alert', 'reviewer_policy' => 'one', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('site_automation_policies')->insert([
            'site_id' => $this->site->id, 'level' => 3, 'rules' => '{}', 'active_profile_id' => $this->profileId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function executedCommand(array $payload = ['url' => 'https://e.ir/booking', 'title' => 'new']): int
    {
        return (int) \DB::table('commands')->insertGetId([
            'site_id' => $this->site->id, 'source_type' => 'test', 'type' => 'update_meta_title', 'risk_tier' => 'R3',
            'payload' => json_encode($payload), 'idempotency_key' => (string) Str::uuid(), 'status' => 'executed',
            'decision_source' => 'policy', 'published_at' => now()->subDays(2), 'expires_at' => now()->addHour(),
            'created_at' => now()->subDays(2), 'updated_at' => now(),
        ]);
    }

    public function test_rollback_restores_and_marks_command(): void
    {
        Http::fake(['*/wp-json/vision-prime/v1/rollback' => Http::response(['status' => 'ack', 'restored' => true, 'result' => ['post_id' => 1, 'restored' => true]])]);
        $command = $this->executedCommand();
        app(CreateRollbackSnapshot::class)->handle($command, 'post:1', ['type' => 'update_meta_title', 'previous' => ['title' => 'old'], 'post_id' => 1]);

        $result = app(RollbackCommand::class)->handle($command);

        $this->assertTrue($result['rolled_back']);
        $this->assertDatabaseHas('commands', ['id' => $command, 'status' => 'rolled_back']);
        $snapshotStatus = \DB::table('rollback_snapshots')->where('command_id', $command)->value('status');
        $this->assertSame('used', $snapshotStatus);
    }

    public function test_rollback_without_snapshot_is_noop(): void
    {
        $command = $this->executedCommand();

        $result = app(RollbackCommand::class)->handle($command);

        $this->assertFalse($result['rolled_back']);
        $this->assertDatabaseHas('commands', ['id' => $command, 'status' => 'executed']);
    }

    public function test_rollback_is_not_marked_when_plugin_does_not_confirm_restore(): void
    {
        // پلاگین ack می‌دهد ولی restore روی وردپرس شکست خورده است — پلتفرم نباید rolled_back ثبت کند.
        Http::fake(['*/wp-json/vision-prime/v1/rollback' => Http::response(['status' => 'ack', 'restored' => false, 'result' => ['error' => 'Rollback payload has no valid post_id.']])]);
        $command = $this->executedCommand();
        app(CreateRollbackSnapshot::class)->handle($command, 'post:1', ['type' => 'update_meta_title', 'previous' => ['title' => 'old'], 'post_id' => 1]);

        $result = app(RollbackCommand::class)->handle($command);

        $this->assertFalse($result['rolled_back']);
        $this->assertDatabaseHas('commands', ['id' => $command, 'status' => 'executed']);
        $snapshotStatus = \DB::table('rollback_snapshots')->where('command_id', $command)->value('status');
        $this->assertSame('available', $snapshotStatus);
    }

    public function test_monitor_rolls_back_when_clicks_drop_below_baseline(): void
    {
        Http::fake(['*/wp-json/vision-prime/v1/rollback' => Http::response(['status' => 'ack', 'restored' => true, 'result' => ['post_id' => 1, 'restored' => true]])]);
        $command = $this->executedCommand();
        app(CreateRollbackSnapshot::class)->handle($command, 'post:1', ['type' => 'update_meta_title', 'previous' => ['title' => 'old'], 'post_id' => 1]);

        $accountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $this->site->organization_id, 'google_subject' => 'sub', 'email' => 'a@b.ir', 'token_ciphertext' => Crypt::encryptString('t'), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $propertyId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $accountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'sc-domain', 'status' => 'selected', 'created_at' => now(), 'updated_at' => now()]);
        $published = now()->subDays(2)->toDateString();
        foreach ([now()->subDays(9), now()->subDays(8), now()->subDays(7), now()->subDays(6), now()->subDays(5), now()->subDays(4), now()->subDays(3)] as $date) {
            \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => $date->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 100, 'impressions' => 1000, 'ctr' => 0.1, 'position' => 5]);
        }
        \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => $published, 'page_url' => 'https://e.ir/booking', 'clicks' => 10, 'impressions' => 1000, 'ctr' => 0.01, 'position' => 9]);
        \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => now()->subDay()->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 12, 'impressions' => 1000, 'ctr' => 0.012, 'position' => 9]);

        (new RollbackMonitor)->handle(app(RollbackCommand::class), app(RecordMetricDropAlert::class));

        $this->assertDatabaseHas('commands', ['id' => $command, 'status' => 'rolled_back']);
    }

    public function test_monitor_alerts_on_r1_drop_without_rollback(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        Membership::query()->create(['organization_id' => $this->site->organization_id, 'user_id' => $user->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $command = $this->executedCommand();
        \DB::table('commands')->where('id', $command)->update(['risk_tier' => 'R1', 'published_at' => now()->subDays(2)]);

        $accountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $this->site->organization_id, 'google_subject' => 'r1-sub', 'email' => 'r1@b.ir', 'token_ciphertext' => Crypt::encryptString('t'), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $propertyId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $accountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'sc-domain', 'status' => 'selected', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([now()->subDays(9), now()->subDays(8), now()->subDays(7), now()->subDays(6), now()->subDays(5), now()->subDays(4), now()->subDays(3)] as $date) {
            \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => $date->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 100, 'impressions' => 1000, 'ctr' => 0.1, 'position' => 5]);
        }
        \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => now()->subDays(2)->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 8, 'impressions' => 1000, 'ctr' => 0.008, 'position' => 9]);
        \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => now()->subDay()->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 10, 'impressions' => 1000, 'ctr' => 0.01, 'position' => 9]);

        (new RollbackMonitor)->handle(app(RollbackCommand::class), app(RecordMetricDropAlert::class));

        // بدون rollback خودکار
        $this->assertDatabaseHas('commands', ['id' => $command, 'status' => 'executed']);
        $this->assertDatabaseHas('connector_events', ['site_id' => $this->site->id, 'type' => 'automation.alert.metric_drop']);
        $this->assertDatabaseHas('notifications', ['type' => AutomationAlert::class]);
    }

    public function test_monitor_dedupes_r1_alert_within_24h(): void
    {
        $command = $this->executedCommand();
        \DB::table('commands')->where('id', $command)->update(['risk_tier' => 'R1']);
        $accountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $this->site->organization_id, 'google_subject' => 'r1d', 'email' => 'r1d@b.ir', 'token_ciphertext' => Crypt::encryptString('t'), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $propertyId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $accountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'sc-domain', 'status' => 'selected', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([now()->subDays(9), now()->subDays(7), now()->subDays(5), now()->subDays(3)] as $date) {
            \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => $date->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 100, 'impressions' => 1000, 'ctr' => 0.1, 'position' => 5]);
        }
        \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => now()->subDays(2)->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 8, 'impressions' => 1000, 'ctr' => 0.008, 'position' => 9]);
        \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => now()->subDay()->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 10, 'impressions' => 1000, 'ctr' => 0.01, 'position' => 9]);

        (new RollbackMonitor)->handle(app(RollbackCommand::class), app(RecordMetricDropAlert::class));
        (new RollbackMonitor)->handle(app(RollbackCommand::class), app(RecordMetricDropAlert::class));

        $this->assertSame(1, \DB::table('connector_events')->where('type', 'automation.alert.metric_drop')->where('site_id', $this->site->id)->count());
    }

    public function test_monitor_keeps_command_when_metrics_are_healthy(): void
    {
        $command = $this->executedCommand();
        app(CreateRollbackSnapshot::class)->handle($command, 'post:1', ['type' => 'update_meta_title', 'previous' => ['title' => 'old'], 'post_id' => 1]);

        $accountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $this->site->organization_id, 'google_subject' => 'sub2', 'email' => 'b@b.ir', 'token_ciphertext' => Crypt::encryptString('t'), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $propertyId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $accountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'sc-domain', 'status' => 'selected', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([now()->subDays(9), now()->subDays(7), now()->subDays(5), now()->subDays(3)] as $date) {
            \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => $date->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 100, 'impressions' => 1000, 'ctr' => 0.1, 'position' => 5]);
        }

        (new RollbackMonitor)->handle(app(RollbackCommand::class), app(RecordMetricDropAlert::class));

        $this->assertDatabaseHas('commands', ['id' => $command, 'status' => 'executed']);
    }
}
