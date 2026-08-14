<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Automation\Actions\AutoPublish;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutoPublishTest extends TestCase
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
            'name' => 'تست L2', 'slug' => 'test-l2', 'kind' => 'custom', 'scope' => 'site',
            'automation_level' => 2, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80,
            'high_risk_threshold' => 90, 'risk_tier_max' => 'R2',
            'enabled_content_types' => json_encode(['meta', 'product'], JSON_UNESCAPED_UNICODE),
            'daily_command_limit' => 10, 'daily_mutation_limit' => 5,
            'rollback_hours' => 168, 'auto_rollback' => false, 'alert_level' => 'warn',
            'reviewer_policy' => 'one', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('site_automation_policies')->insert([
            'site_id' => $this->site->id, 'level' => 2,
            'rules' => json_encode(['max_risk_tier' => 'R2', 'allowed_command_types' => ['update_meta_title', 'update_meta_description', 'update_product_title', 'update_product_description']], JSON_UNESCAPED_UNICODE),
            'active_profile_id' => $this->profileId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(['*/wp-json/vision-prime/v1/commands' => Http::response(['ok' => true, 'result' => ['post_id' => 42, 'previous' => 'old', 'new' => 'new']])]);
    }

    private function command(string $status = 'pending_approval', string $risk = 'R1', ?int $confidence = 85): int
    {
        return (int) \DB::table('commands')->insertGetId([
            'site_id' => $this->site->id, 'source_type' => 'test', 'type' => 'update_meta_title',
            'risk_tier' => $risk, 'payload' => json_encode(['url' => 'https://e.ir/x', 'title' => 'new']),
            'idempotency_key' => (string) Str::uuid(), 'status' => $status,
            'confidence_score' => $confidence,
            'expires_at' => now()->addHour(), 'policy_version' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_auto_publish_executes_with_system_reviewer_and_policy_source(): void
    {
        $id = $this->command();

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('auto_publish', $result['decision']);
        $this->assertTrue($result['executed']);
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'executed', 'decision_source' => 'policy']);
        $this->assertNotNull(\DB::table('commands')->where('id', $id)->value('published_at'));
        $this->assertDatabaseHas('command_approvals', [
            'command_id' => $id, 'reviewer_id' => null, 'reviewer_type' => 'system', 'decision' => 'auto_approved',
        ]);
        $snapshot = json_decode((string) \DB::table('command_approvals')->where('command_id', $id)->value('policy_snapshot'), true);
        $this->assertSame(2, $snapshot['automation_level']);
        $this->assertSame(3, $snapshot['policy_version']);
        $this->assertSame(80, $snapshot['confidence_threshold']);
    }

    public function test_l1_policy_leaves_command_pending(): void
    {
        \DB::table('site_automation_policies')->where('site_id', $this->site->id)->update(['level' => 1]);
        \DB::table('automation_profiles')->where('id', $this->profileId)->update(['automation_level' => 1, 'ai_policy' => 'draft_only']);
        $id = $this->command();

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('pending_approval', $result['decision']);
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'pending_approval', 'decision_source' => null]);
    }

    public function test_confidence_below_threshold_stays_pending(): void
    {
        $id = $this->command(confidence: 70);

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('pending_approval', $result['decision']);
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'pending_approval']);
    }

    public function test_daily_cap_delays_to_queued(): void
    {
        \DB::table('automation_profiles')->where('id', $this->profileId)->update(['daily_command_limit' => 0]);
        $id = $this->command();

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('delayed', $result['decision']);
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'queued']);
    }

    public function test_disallowed_type_is_rejected(): void
    {
        \DB::table('site_automation_policies')->where('site_id', $this->site->id)->update([
            'rules' => json_encode(['max_risk_tier' => 'R2', 'allowed_command_types' => ['update_meta_title']], JSON_UNESCAPED_UNICODE),
        ]);
        \DB::table('commands')->where('id', $id = $this->command())->update(['type' => 'update_content', 'risk_tier' => 'R2']);

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('rejected', $result['decision']);
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'cancelled']);
    }

    public function test_emergency_stop_blocks_auto_publish(): void
    {
        \DB::table('site_automation_policies')->where('site_id', $this->site->id)->update(['emergency_stopped_at' => now()]);
        $id = $this->command();

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('rejected', $result['decision']);
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'cancelled']);
    }

    public function test_content_type_routing_overrides_active_profile(): void
    {
        $l3Profile = \DB::table('automation_profiles')->insertGetId([
            'name' => 'تست L3 متا', 'slug' => 'test-l3-meta', 'kind' => 'custom', 'scope' => 'site',
            'automation_level' => 3, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 90,
            'high_risk_threshold' => 95, 'risk_tier_max' => 'R2', 'auto_rollback' => true,
            'enabled_content_types' => json_encode(['meta'], JSON_UNESCAPED_UNICODE),
            'daily_command_limit' => 25, 'daily_mutation_limit' => 10, 'rollback_hours' => 336,
            'alert_level' => 'alert', 'reviewer_policy' => 'one', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('site_profile_routes')->insert(['site_id' => $this->site->id, 'profile_id' => $l3Profile, 'content_type' => 'meta', 'created_at' => now(), 'updated_at' => now()]);
        $id = $this->command(); // confidence 85 < آستانهٔ ۹۰ پروفایل route شده

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('pending_approval', $result['decision']);
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'pending_approval']);

        \DB::table('site_profile_routes')->where('site_id', $this->site->id)->delete();
        $id2 = $this->command();
        $result2 = app(AutoPublish::class)->handle($id2);
        $this->assertSame('auto_publish', $result2['decision']);
    }

    public function test_already_processed_command_is_noop(): void
    {
        $id = $this->command(status: 'executed', confidence: null);

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('noop', $result['decision']);
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'executed']);
    }
}
