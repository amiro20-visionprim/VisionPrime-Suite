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

/**
 * گیت‌های هاردکد فاز ۰ در مسیر کامل AutoPublish:
 *  ۱) auto_publish_scope بسته → pending (حتی با همهٔ شرایط مساعد)
 *  ۲) گرمایش ناقص → pending (حتی با scope و کیفیت پاس)
 *  ۳) گیت کیفیت (بدنهٔ کوتاه/placeholder) → pending
 *  ۴) همهٔ گیت‌ها پاس → auto_publish
 */
class AutoPublishGuardrailsTest extends TestCase
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
            'name' => 'تست L3', 'slug' => 'test-l3', 'kind' => 'custom', 'scope' => 'site',
            'automation_level' => 3, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80,
            'high_risk_threshold' => 90, 'risk_tier_max' => 'R2',
            'enabled_content_types' => json_encode(['article'], JSON_UNESCAPED_UNICODE),
            'daily_command_limit' => 25, 'daily_mutation_limit' => 10,
            'rollback_hours' => 336, 'auto_rollback' => true, 'alert_level' => 'alert',
            'reviewer_policy' => 'one', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('site_automation_policies')->insert([
            'site_id' => $this->site->id, 'level' => 3,
            'rules' => json_encode(['max_risk_tier' => 'R2', 'allowed_command_types' => ['update_content']], JSON_UNESCAPED_UNICODE),
            'active_profile_id' => $this->profileId,
            'auto_publish_scope' => 'article',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(['*/wp-json/vision-prime/v1/commands' => Http::response(['ok' => true, 'result' => ['post_id' => 42, 'previous' => 'old', 'new' => 'new']])]);
    }

    private function articleCommand(string $body, string $status = 'pending_approval', string $risk = 'R2'): int
    {
        return (int) \DB::table('commands')->insertGetId([
            'site_id' => $this->site->id, 'source_type' => 'test', 'type' => 'update_content',
            'content_type' => 'article',
            'risk_tier' => $risk, 'payload' => json_encode(['url' => 'https://e.ir/x', 'content' => $body]),
            'idempotency_key' => (string) Str::uuid(), 'status' => $status,
            'confidence_score' => 95,
            'expires_at' => now()->addHour(), 'policy_version' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function warmup(int $count = 5): void
    {
        for ($i = 0; $i < $count; $i++) {
            \DB::table('commands')->insert([
                'site_id' => $this->site->id, 'source_type' => 'test', 'type' => 'update_content',
                'content_type' => 'article', 'risk_tier' => 'R2', 'payload' => '{}',
                'idempotency_key' => (string) Str::uuid(), 'status' => 'executed',
                'decision_source' => 'manual',
                'expires_at' => now()->addHour(), 'policy_version' => 1,
                'created_at' => now()->subDays($i + 1), 'updated_at' => now(),
            ]);
        }
    }

    public function test_closed_scope_blocks_auto_publish(): void
    {
        $this->warmup();
        \DB::table('site_automation_policies')->where('site_id', $this->site->id)->update(['auto_publish_scope' => 'none']);
        $paragraph = str_repeat('متن کامل آموزش سئو برای بهبود رتبه سایت شما در نتایج جستجو. ', 60);
        $id = $this->articleCommand('<h2>مقدمه</h2><p>'.$paragraph.'</p>');

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('pending_approval', $result['decision']);
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'pending_approval']);
    }

    public function test_insufficient_warmup_blocks_auto_publish(): void
    {
        $this->warmup(2); // فقط ۲ اجرای انسانی — مقاله به ۵ نیاز دارد
        $paragraph = str_repeat('متن کامل آموزش سئو برای بهبود رتبه سایت شما در نتایج جستجو. ', 60);
        $id = $this->articleCommand('<h2>مقدمه</h2><p>'.$paragraph.'</p>');

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('pending_approval', $result['decision']);
        $this->assertStringContainsString('Warm-up', $result['reason']);
    }

    public function test_low_quality_content_blocks_auto_publish(): void
    {
        $this->warmup();
        $id = $this->articleCommand('<p>کوتاه است و placeholder دارد lorem ipsum.</p>');

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('pending_approval', $result['decision']);
        $this->assertStringContainsString('quality', strtolower($result['reason']));
    }

    public function test_all_gates_pass_auto_publishes(): void
    {
        $this->warmup();
        $paragraph = str_repeat('متن کامل آموزش سئو برای بهبود رتبه سایت شما در نتایج جستجو. ', 60);
        $body = '<h2>مقدمه</h2><p>'.$paragraph.'</p>'
            .'<h2>مراحل</h2><p>'.$paragraph.'</p>'
            .'<h2>جمع‌بندی</h2><p>'.$paragraph.'</p>';
        $id = $this->articleCommand($body);

        $result = app(AutoPublish::class)->handle($id);

        $this->assertSame('auto_publish', $result['decision']);
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'executed', 'decision_source' => 'policy']);
    }
}
