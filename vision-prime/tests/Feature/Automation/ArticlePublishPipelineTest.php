<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Ai\Actions\DecideReviewItem;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\ContentStandardsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * فاز ۲ — جریان کامل: تأیید پیش‌نویس مقاله → ساخت کامند publish_new_article →
 * گیت‌های auto_publish (scope=article + گرمایش + کیفیت + اطمینان) → انتشار روی وردپرس.
 */
class ArticlePublishPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private int $profileId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentStandardsSeeder::class);
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $this->site = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        \DB::table('site_connections')->insert(['site_id' => $this->site->id, 'status' => 'connected', 'platform_url' => 'https://wp.test', 'secret_ciphertext' => Crypt::encryptString('secret'), 'created_at' => now(), 'updated_at' => now()]);
        // GSC تازه برای data_quality کامل
        $gscAccountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $o->id, 'google_subject' => 'acct:'.Str::random(8), 'email' => 'gsc@test.local', 'token_ciphertext' => Crypt::encryptString('token'), 'token_expires_at' => now()->addDay(), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $propId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $gscAccountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'site', 'status' => 'selected', 'selected_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('gsc_import_runs')->insert(['gsc_property_id' => $propId, 'status' => 'completed', 'date_start' => now()->subDays(2)->toDateString(), 'date_end' => now()->toDateString(), 'finished_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()]);
        $this->profileId = \DB::table('automation_profiles')->insertGetId([
            'name' => 'تست L3', 'slug' => 'test-l3', 'kind' => 'custom', 'scope' => 'site',
            'automation_level' => 3, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80,
            // برای R3، آستانهٔ اطمینان پروفایل ۸۵ است (منبع rule_based → امتیاز ~۸۵) — پاس می‌شود
            'high_risk_threshold' => 85, 'risk_tier_max' => 'R3',
            'enabled_content_types' => json_encode(['article'], JSON_UNESCAPED_UNICODE),
            'daily_command_limit' => 25, 'daily_mutation_limit' => 10,
            'rollback_hours' => 336, 'auto_rollback' => true, 'alert_level' => 'alert',
            'reviewer_policy' => 'one', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('site_automation_policies')->insert([
            'site_id' => $this->site->id, 'level' => 3,
            'rules' => json_encode(['max_risk_tier' => 'R3', 'allowed_command_types' => ['publish_new_article']], JSON_UNESCAPED_UNICODE),
            'active_profile_id' => $this->profileId,
            'auto_publish_scope' => 'article',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(['*/wp-json/vision-prime/v1/commands' => Http::response(['ok' => true, 'result' => ['post_id' => 42, 'previous' => ['created' => true, 'post_id' => 42], 'new_length' => 1000]], 200)]);
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

    /** ساخت پیش‌نویس مقالهٔ تأییدشده + review item. */
    private function approvedArticleDraft(string $title = 'آموزش سئو: راهنمای جامع بهینه‌سازی سایت'): int
    {
        $profileId = \DB::table('url_profiles')->insertGetId([
            'site_id' => $this->site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://e.ir/blog/seo-guide/',
            'content_type' => 'page',
            'post_status' => 'publish',
            'metadata' => json_encode(['gsc' => ['clicks' => 30, 'impressions' => 1200, 'ctr' => 0.025, 'position' => 11]], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $paragraph = str_repeat('متن کامل مقاله درباره سئو برای بهبود رتبه سایت شما در نتایج جستجو. ', 40);
        $link = '<a href="https://e.ir/blog/internal-link">لینک داخلی</a>';
        $content = '<h1>'.$title.'</h1><h2>مقدمه</h2><p>'.$paragraph.' '.$link.'</p>'
            .'<h2>مراحل اجرا</h2><ol><li>گام اول: تحلیل وضعیت فعلی</li><li>گام دوم: اجرای بهینه‌سازی</li><li>گام سوم: اندازه‌گیری نتیجه</li></ol><p>'.$link.'</p>'
            .'<h2>سؤالات متداول</h2><p><strong>پرسش:</strong> آیا سئو مهم است؟ <strong>پاسخ:</strong> بله، برای دیده شدن در جستجو حیاتی است.</p>'
            .'<h2>گام بعدی</h2><p>برای مشاوره رایگان همین حالا با تیم ما تماس بگیرید. '.$link.'</p>'
            .'<h2>جمع‌بندی</h2><p>'.$paragraph.'</p>';
        $output = [
            'kind' => 'article',
            'text' => $content,
            'model' => 'rule-based',
            'source' => 'rule_based',
            'standard' => ['standard_key' => 'article×guide×informational', 'word_min' => 400, 'word_max' => 2000, 'required_elements' => ['faq', 'cta'], 'min_headings' => 3],
            'profile' => ['content_type' => 'article', 'subtype' => 'guide', 'intent' => 'informational', 'title' => $title],
            'featured_image' => ['alt' => $title, 'suggested_width' => 1200, 'suggested_height' => 630, 'aspect' => '1200:630', 'rationale' => 'x'],
            'schema' => [['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $title]],
        ];
        $genId = \DB::table('ai_generations')->insertGetId([
            'site_id' => $this->site->id, 'template_id' => null,
            'input_redacted' => json_encode(['kind' => 'article', 'url' => 'https://e.ir/blog/seo-guide/', 'target_query' => 'آموزش سئو', 'profile' => ['subtype' => 'guide']], JSON_UNESCAPED_UNICODE),
            'output_status' => 'needs_review', 'usage' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $versionId = \DB::table('ai_generation_versions')->insertGetId(['generation_id' => $genId, 'version' => 1, 'output' => json_encode($output, JSON_UNESCAPED_UNICODE), 'status' => 'needs_review', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('ai_generations')->where('id', $genId)->update(['current_version_id' => $versionId]);

        return (int) \DB::table('review_items')->insertGetId([
            'site_id' => $this->site->id, 'subject_type' => 'ai_generation', 'subject_id' => $genId,
            'status' => 'pending_review', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_approving_article_draft_creates_publish_command_and_auto_publishes(): void
    {
        $this->warmup();
        $review = $this->approvedArticleDraft();
        $user = User::factory()->create();

        $result = app(DecideReviewItem::class)->handle($review, $user, 'approved', 'ok');

        $this->assertSame('approved', $result['status']);
        $this->assertSame('auto_publish', $result['auto_publish_decision']);
        $this->assertNotNull($result['command_id']);

        // کامند publish_new_article ساخته و با تأیید سیستمی اجرا شده است
        $command = \DB::table('commands')->where('id', $result['command_id'])->first();
        $this->assertSame('publish_new_article', $command->type);
        $this->assertSame('article', $command->content_type);
        $this->assertSame('R3', $command->risk_tier);
        $this->assertSame('executed', $command->status);
        $this->assertSame('policy', $command->decision_source);
        $this->assertGreaterThanOrEqual(85, (int) $command->confidence_score);

        $payload = json_decode($command->payload, true);
        $this->assertSame('آموزش سئو: راهنمای جامع بهینه‌سازی سایت', $payload['title']);
        $this->assertStringContainsString('<h1>', (string) $payload['content']);
        $this->assertSame('seo-guide', $payload['slug']);
        $this->assertSame('آموزش سئو', $payload['target_query']);
        $this->assertNotEmpty($payload['schema']);

        // تأیید سیستمی ثبت شده و به وردپرس ارسال شده است
        $this->assertDatabaseHas('command_approvals', ['command_id' => $command->id, 'reviewer_type' => 'system', 'decision' => 'auto_approved']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'wp-json/vision-prime/v1/commands'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'article_draft.approved_pipeline']);
    }

    public function test_approving_article_draft_without_warmup_keeps_command_pending(): void
    {
        $this->warmup(2); // گرمایش ناقص برای article (نیاز ۵)
        $review = $this->approvedArticleDraft();
        $user = User::factory()->create();

        $result = app(DecideReviewItem::class)->handle($review, $user, 'approved');

        $this->assertSame('approved', $result['status']);
        $this->assertSame('pending_approval', $result['auto_publish_decision']);
        $this->assertStringContainsString('Warm-up', (string) $result['auto_publish_reason']);

        $command = \DB::table('commands')->where('id', $result['command_id'])->first();
        $this->assertSame('pending_approval', $command->status);
        Http::assertNothingSent();
    }

    /** ساخت پیش‌نویس محصول تأییدشده + review item (kind=product). */
    private function approvedProductDraft(): int
    {
        $profileId = \DB::table('url_profiles')->insertGetId([
            'site_id' => $this->site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://e.ir/product/headset-x/',
            'content_type' => 'product',
            'post_status' => 'publish',
            'metadata' => json_encode(['gsc' => ['clicks' => 8, 'impressions' => 400, 'ctr' => 0.02, 'position' => 15]], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $sentences = [
            'کیفیت صدای این مدل با درایورهای پیشرفته برای موسیقی و مکالمه عالی است.',
            'باتری آن تا چهل ساعت کارکرد مداوم را برای استفاده روزانه تضمین می‌کند.',
            'اتصال پایدار بلوتوث و میکروفون نویزگیر، تجربهٔ تماس را بهبود می‌بخشد.',
            'طراحی سبک و هدفون‌های نرم، استفادهٔ طولانی را راحت می‌کند.',
            'کنترل‌های لمسی و اپلیکیشن همراه، تنظیم صدا را ساده می‌کنند.',
        ];
        $paragraph = implode(' ', array_map(fn (string $s): string => str_repeat($s.' ', 6), $sentences));
        $content = '<h1>هدفون بی‌سیم پرو</h1><h2>ویژگی‌های کلیدی</h2><p>'.$paragraph.'</p>'
            .'<h2>مزایا و معایب</h2><p><strong>مزایا:</strong> کیفیت صدا عالی است. <strong>معایب:</strong> قیمت بالاتر از میانگین است.</p>'
            .'<h2>گام بعدی</h2><p>برای ثبت سفارش همین حالا اقدام کنید.</p>';
        $output = [
            'kind' => 'product',
            'text' => $content,
            'model' => 'rule-based',
            'source' => 'rule_based',
            'standard' => ['standard_key' => 'product×long_desc×commercial', 'word_min' => 200, 'word_max' => 600, 'required_elements' => ['h2_structure', 'pros_cons', 'cta'], 'min_headings' => 2],
            'profile' => ['content_type' => 'product', 'subtype' => 'long_desc', 'intent' => 'commercial', 'title' => 'هدفون بی‌سیم پرو'],
            'featured_image' => ['alt' => 'هدفون بی‌سیم پرو', 'suggested_width' => 1200, 'suggested_height' => 1200, 'aspect' => '1200:1200', 'rationale' => 'x'],
            'schema' => [['@context' => 'https://schema.org', '@type' => 'Product', 'name' => 'هدفون بی‌سیم پرو']],
        ];
        $genId = \DB::table('ai_generations')->insertGetId([
            'site_id' => $this->site->id, 'template_id' => null,
            'input_redacted' => json_encode(['kind' => 'product', 'url' => 'https://e.ir/product/headset-x/', 'target_query' => 'هدفون بی‌سیم', 'profile' => ['subtype' => 'long_desc']], JSON_UNESCAPED_UNICODE),
            'output_status' => 'needs_review', 'usage' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $versionId = \DB::table('ai_generation_versions')->insertGetId(['generation_id' => $genId, 'version' => 1, 'output' => json_encode($output, JSON_UNESCAPED_UNICODE), 'status' => 'needs_review', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('ai_generations')->where('id', $genId)->update(['current_version_id' => $versionId]);

        return (int) \DB::table('review_items')->insertGetId([
            'site_id' => $this->site->id, 'subject_type' => 'ai_generation', 'subject_id' => $genId,
            'status' => 'pending_review', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** گرمایش از نوع product (نیاز ۳). */
    private function productWarmup(int $count = 3): void
    {
        for ($i = 0; $i < $count; $i++) {
            \DB::table('commands')->insert([
                'site_id' => $this->site->id, 'source_type' => 'test', 'type' => 'update_product_title',
                'content_type' => 'product', 'risk_tier' => 'R2', 'payload' => '{}',
                'idempotency_key' => (string) Str::uuid(), 'status' => 'executed',
                'decision_source' => 'manual',
                'expires_at' => now()->addHour(), 'policy_version' => 1,
                'created_at' => now()->subDays($i + 1), 'updated_at' => now(),
            ]);
        }
    }

    public function test_approving_product_draft_uses_product_gates_scope_product_warmup_3(): void
    {
        // پالیسی با auto_publish_scope=product (نه article) + پروفایل فعال برای product
        \DB::table('site_automation_policies')->where('site_id', $this->site->id)->update([
            'auto_publish_scope' => 'product',
            'rules' => json_encode(['max_risk_tier' => 'R3', 'allowed_command_types' => ['publish_new_article']], JSON_UNESCAPED_UNICODE),
        ]);
        \DB::table('automation_profiles')->where('id', $this->profileId)->update([
            'enabled_content_types' => json_encode(['product'], JSON_UNESCAPED_UNICODE),
        ]);
        $this->productWarmup(); // گرمایش ۳ از نوع product
        $review = $this->approvedProductDraft();
        $user = User::factory()->create();

        $result = app(DecideReviewItem::class)->handle($review, $user, 'approved', 'ok');

        $this->assertSame('approved', $result['status']);
        $this->assertSame('auto_publish', $result['auto_publish_decision']);

        $command = \DB::table('commands')->where('id', $result['command_id'])->first();
        $this->assertSame('publish_new_article', $command->type);
        $this->assertSame('product', $command->content_type);
        $this->assertSame('executed', $command->status);

        $payload = json_decode($command->payload, true);
        $this->assertSame('product', $payload['content_type']);
        $this->assertSame('headset-x', $payload['slug']);

        // snapshot گیت‌ها شامل دامنهٔ product و گرمایش ۳ است
        $approval = \DB::table('command_approvals')->where('command_id', $command->id)->where('reviewer_type', 'system')->first();
        $snapshot = json_decode($approval->policy_snapshot, true);
        $this->assertSame('product', $snapshot['auto_publish_scope']);
        $this->assertSame(3, $snapshot['warmup_required']);
        $this->assertSame(3, $snapshot['warmup_count']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'wp-json/vision-prime/v1/commands'));
    }

    public function test_product_draft_without_warmup_keeps_command_pending(): void
    {
        \DB::table('site_automation_policies')->where('site_id', $this->site->id)->update([
            'auto_publish_scope' => 'product',
        ]);
        \DB::table('automation_profiles')->where('id', $this->profileId)->update([
            'enabled_content_types' => json_encode(['product'], JSON_UNESCAPED_UNICODE),
        ]);
        $this->productWarmup(1); // گرمایش ناقص برای product (نیاز ۳)
        $review = $this->approvedProductDraft();
        $user = User::factory()->create();

        $result = app(DecideReviewItem::class)->handle($review, $user, 'approved');

        $this->assertSame('pending_approval', $result['auto_publish_decision']);
        $this->assertStringContainsString('Warm-up', (string) $result['auto_publish_reason']);

        $command = \DB::table('commands')->where('id', $result['command_id'])->first();
        $this->assertSame('pending_approval', $command->status);
        Http::assertNothingSent();
    }

    public function test_commands_index_exposes_auto_publish_details_for_published_article(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->warmup();
        $review = $this->approvedArticleDraft();
        $user = User::factory()->create();

        $result = app(DecideReviewItem::class)->handle($review, $user, 'approved', 'ok');
        $this->assertSame('auto_publish', $result['auto_publish_decision']);

        $org = $this->site->organization;
        \DB::table('memberships')->insert([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role_id' => \DB::table('roles')->where('key', 'agency-admin')->value('id'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $response = $this->actingAs($user)->withSession(['current_organization_id' => $org->id])
            ->get('/app/commands');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('App/Commands/Index')
            ->has('commands.data', 6) // ۵ گرمایش + ۱ انتشار خودکار
            ->where('commands.data.0.type', 'publish_new_article')
            ->where('commands.data.0.content_type', 'article')
            ->where('commands.data.0.status', 'executed')
            ->where('commands.data.0.site_name', 'S')
            ->where('commands.data.0.platform_url', 'https://wp.test')
            ->where('commands.data.0.auto_approved', true)
            ->where('commands.data.0.post_id', 42)
            ->where('commands.data.0.post_url', 'https://wp.test/?p=42')
            ->where('commands.data.0.gate_snapshot.warmup_required', 5)
            ->where('commands.data.0.gate_snapshot.auto_publish_scope', 'article')
            ->where('commands.data.0.confidence_factors.human_approved', true)
        );
    }

    public function test_review_detail_exposes_live_command_status_after_approval(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->warmup();
        $review = $this->approvedArticleDraft();
        $user = User::factory()->create();

        $result = app(DecideReviewItem::class)->handle($review, $user, 'approved', 'ok');
        $this->assertSame('auto_publish', $result['auto_publish_decision']);

        // دادهٔ GSC برای گزارش تأثیر (پنجرهٔ قبل و بعد از انتشار)
        $propId = \DB::table('gsc_properties')->where('site_id', $this->site->id)->value('id');
        foreach ([now()->subDays(4)->toDateString() => [2, 18.0], now()->subDays(3)->toDateString() => [3, 16.5]] as $date => [$clicks, $pos]) {
            \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propId, 'date' => $date, 'page_url' => 'https://wp.test/seo-guide/', 'clicks' => $clicks, 'impressions' => 500, 'ctr' => 0.02, 'position' => $pos]);
        }
        foreach ([now()->addDays(3)->toDateString() => [9, 7.0], now()->addDays(4)->toDateString() => [11, 6.0]] as $date => [$clicks, $pos]) {
            \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propId, 'date' => $date, 'page_url' => 'https://wp.test/seo-guide/', 'clicks' => $clicks, 'impressions' => 600, 'ctr' => 0.02, 'position' => $pos]);
        }

        $org = $this->site->organization;
        \DB::table('memberships')->insert([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role_id' => \DB::table('roles')->where('key', 'agency-admin')->value('id'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $response = $this->actingAs($user)->withSession(['current_organization_id' => $org->id])
            ->get('/app/reviews/'.$review);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('App/Reviews/Show')
            ->where('subject.generation.command.status', 'executed')
            ->where('subject.generation.command.type', 'publish_new_article')
            ->where('subject.generation.command.content_type', 'article')
            ->where('subject.generation.command.auto_approved', true)
            ->where('subject.generation.command.post_id', 42)
            ->where('subject.generation.command.post_url', 'https://wp.test/?p=42')
            ->where('subject.generation.command.gate_snapshot.warmup_required', 5)
            // گزارش تأثیر GSC در ویجت کامند
            ->where('subject.generation.command.impact.status', 'ready')
            ->where('subject.generation.command.impact.verdict', 'improved')
            ->where('subject.generation.command.impact.delta.clicks', 15)
        );
    }

    public function test_review_detail_includes_real_woocommerce_price_and_stock(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->productWarmup();
        $review = $this->approvedProductDraft();
        $user = User::factory()->create();

        // کانکتور ووکامرس: پاسخ واقعی قیمت/موجودی
        Http::fake([
            '*/wp-json/vision-prime/v1/product-info' => Http::response([
                'post_id' => 42,
                'title' => 'هدفون بی‌سیم پرو',
                'post_type' => 'product',
                'url' => 'https://wp.test/product/headset-x/',
                'is_product' => true,
                'price' => '2450000',
                'regular_price' => '2900000',
                'sale_price' => '2450000',
                'currency' => 'IRR',
                'stock_quantity' => 12,
                'stock_status' => 'instock',
                'in_stock' => true,
            ], 200),
        ]);

        $org = $this->site->organization;
        \DB::table('memberships')->insert([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role_id' => \DB::table('roles')->where('key', 'agency-admin')->value('id'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $response = $this->actingAs($user)->withSession(['current_organization_id' => $org->id])
            ->get('/app/reviews/'.$review);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('App/Reviews/Show')
            ->where('subject.generation.kind', 'product')
            ->where('subject.generation.woo_product.is_product', true)
            ->where('subject.generation.woo_product.price', '2450000')
            ->where('subject.generation.woo_product.stock_quantity', 12)
            ->where('subject.generation.woo_product.in_stock', true)
        );
    }

    public function test_rejecting_article_draft_does_not_create_command(): void
    {
        $this->warmup();
        $review = $this->approvedArticleDraft();
        $user = User::factory()->create();

        $result = app(DecideReviewItem::class)->handle($review, $user, 'rejected', 'poor quality');

        $this->assertSame('rejected', $result['status']);
        $this->assertArrayNotHasKey('command_id', $result);
        $this->assertDatabaseMissing('commands', ['site_id' => $this->site->id, 'type' => 'publish_new_article']);
    }
}
