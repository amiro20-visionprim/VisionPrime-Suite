<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Automation\Actions\AutoPublish;
use App\Domains\Automation\Actions\CreateRollbackSnapshot;
use App\Domains\Automation\Actions\EmergencyStop;
use App\Domains\Automation\Actions\RecordMetricDropAlert;
use App\Domains\Automation\Actions\ResumeAutomation;
use App\Domains\Automation\Actions\RollbackCommand;
use App\Domains\Automation\Jobs\LearningLoop;
use App\Domains\Automation\Jobs\RollbackMonitor;
use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Acceptance نهایی D-013 — اثبات کل زنجیره در یک آزمون (بند Acceptance سند ۰۱):
 * AutoPublish → سقف روزانه (queued) → توقف اضطراری (cancel صف) → رفع توقف →
 * انتشار دوباره → بازگشت خودکار R3 (افت زیر baseline) → حلقهٔ یادگیری.
 */
class DAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Site $site;

    private int $profileId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->organization = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o-'.Str::random(5), 'status' => 'active']);
        $user = User::factory()->create();
        Membership::query()->create(['organization_id' => $this->organization->id, 'user_id' => $user->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $this->organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $this->organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $this->site = Site::query()->create(['organization_id' => $this->organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        \DB::table('site_connections')->insert(['site_id' => $this->site->id, 'status' => 'connected', 'platform_url' => 'https://wp.test', 'secret_ciphertext' => Crypt::encryptString('secret'), 'created_at' => now(), 'updated_at' => now()]);
        $this->profileId = \DB::table('automation_profiles')->insertGetId([
            'name' => 'Acceptance L2', 'slug' => 'acc-l2', 'kind' => 'custom', 'scope' => 'site',
            'automation_level' => 2, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80,
            'high_risk_threshold' => 90, 'risk_tier_max' => 'R3', 'auto_rollback' => true,
            'enabled_content_types' => json_encode(['meta'], JSON_UNESCAPED_UNICODE),
            'daily_command_limit' => 1, 'daily_mutation_limit' => 5, 'rollback_hours' => 336,
            'alert_level' => 'alert', 'reviewer_policy' => 'one', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('site_automation_policies')->insert([
            'site_id' => $this->site->id, 'level' => 2,
            'rules' => json_encode(['allowed_command_types' => ['update_meta_title']], JSON_UNESCAPED_UNICODE),
            'active_profile_id' => $this->profileId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake([
            '*/wp-json/vision-prime/v1/commands' => Http::response(['ok' => true, 'result' => ['post_id' => 1, 'previous' => ['title' => 'old'], 'new' => 'new']]),
            '*/wp-json/vision-prime/v1/rollback' => Http::response(['status' => 'ack']),
        ]);
    }

    private function command(string $risk = 'R1', ?int $confidence = 85, string $status = 'pending_approval', ?Carbon $publishedAt = null): int
    {
        return (int) \DB::table('commands')->insertGetId([
            'site_id' => $this->site->id, 'source_type' => 'test', 'type' => 'update_meta_title', 'risk_tier' => $risk,
            'payload' => json_encode(['url' => 'https://e.ir/booking', 'title' => 'new'], JSON_UNESCAPED_UNICODE),
            'idempotency_key' => (string) Str::uuid(), 'status' => $status, 'confidence_score' => $confidence,
            'decision_source' => $status === 'executed' ? 'policy' : null,
            'published_at' => $publishedAt, 'expires_at' => now()->addHours(2), 'policy_version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_full_automation_chain_end_to_end(): void
    {
        // ۱) انتشار خودکار: A بالای آستانه → اجرا با تأیید سیستم
        $a = $this->command();
        $this->assertSame('auto_publish', app(AutoPublish::class)->handle($a)['decision']);
        $this->assertDatabaseHas('commands', ['id' => $a, 'status' => 'executed', 'decision_source' => 'policy']);
        $this->assertDatabaseHas('command_approvals', ['command_id' => $a, 'reviewer_type' => 'system', 'decision' => 'auto_approved']);
        $this->assertNotNull(\DB::table('commands')->where('id', $a)->value('published_at'));

        // ۲) سقف روزانه (daily_command_limit=1): B → صف
        $b = $this->command();
        $this->assertSame('delayed', app(AutoPublish::class)->handle($b)['decision']);
        $this->assertDatabaseHas('commands', ['id' => $b, 'status' => 'queued']);

        // ۳) توقف اضطراری → cancel دستور در صف؛ دستور تأییدشده دست نمی‌خورد
        app(EmergencyStop::class)->handle($this->site->id);
        $this->assertDatabaseHas('commands', ['id' => $b, 'status' => 'cancelled']);
        $this->assertNotNull(\DB::table('site_automation_policies')->where('site_id', $this->site->id)->value('emergency_stopped_at'));

        // ۴) رفع توقف
        app(ResumeAutomation::class)->handle($this->site->id);
        $this->assertNull(\DB::table('site_automation_policies')->where('site_id', $this->site->id)->value('emergency_stopped_at'));

        // ۵) انتشار دوباره بعد از رفع توقف (سقف را بالا می‌بریم تا C خودکار شود)
        \DB::table('automation_profiles')->where('id', $this->profileId)->update(['daily_command_limit' => 10]);
        $c = $this->command();
        $this->assertSame('auto_publish', app(AutoPublish::class)->handle($c)['decision']);
        $this->assertDatabaseHas('commands', ['id' => $c, 'status' => 'executed']);

        // ۶) بازگشت خودکار R3 وقتی بازدید زیر baseline افتاد
        $d = $this->command(risk: 'R3', status: 'executed', publishedAt: now()->subDays(2));
        app(CreateRollbackSnapshot::class)->handle($d, 'post:1', ['type' => 'update_meta_title', 'previous' => ['title' => 'old'], 'post_id' => 1]);
        $accountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $this->organization->id, 'google_subject' => 'acc', 'email' => 'acc@b.ir', 'token_ciphertext' => Crypt::encryptString('t'), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $propertyId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $accountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'sc-domain', 'status' => 'selected', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([now()->subDays(9), now()->subDays(8), now()->subDays(7), now()->subDays(6), now()->subDays(5), now()->subDays(4), now()->subDays(3)] as $date) {
            \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => $date->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 100, 'impressions' => 1000, 'ctr' => 0.1, 'position' => 5]);
        }
        \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => now()->subDays(2)->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 8, 'impressions' => 1000, 'ctr' => 0.008, 'position' => 9]);
        \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => now()->subDay()->toDateString(), 'page_url' => 'https://e.ir/booking', 'clicks' => 10, 'impressions' => 1000, 'ctr' => 0.01, 'position' => 9]);
        (new RollbackMonitor)->handle(app(RollbackCommand::class), app(RecordMetricDropAlert::class));
        $this->assertDatabaseHas('commands', ['id' => $d, 'status' => 'rolled_back']);

        // ۷) حلقهٔ یادگیری: از وضعیت واقعی (A، C موفق؛ D بازگشت‌خورده) نرخ می‌سازد
        (new LearningLoop(siteId: $this->site->id))->handle();
        $this->assertDatabaseHas('automation_learning_history', [
            'site_id' => $this->site->id, 'command_type' => 'update_meta_title', 'total' => 3, 'successful' => 2,
        ]);
    }
}
