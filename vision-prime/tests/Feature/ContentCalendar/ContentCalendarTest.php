<?php

declare(strict_types=1);

namespace Tests\Feature\ContentCalendar;

use App\Domains\Ai\Actions\DecideReviewItem;
use App\Domains\Automation\Actions\AutoPublish;
use App\Domains\Automation\Actions\SchedulePublish;
use App\Domains\Automation\Jobs\ReleaseScheduledCommands;
use App\Domains\Automation\Jobs\RemindScheduledPublishes;
use App\Domains\Automation\Services\SuggestPublishSlot;
use App\Domains\Gsc\Actions\UpsertGscMetric;
use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * تقویم محتوایی — زمان‌بندی انتشار پیش‌نویس‌های مقاله/محصول:
 * اکشن زمان‌بندی/لغو، job آزادسازی موعدرسیده، و ایزوله‌سازی سازمانی کنترلر.
 */
class ContentCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Organization $foreign;

    private User $user;

    private Site $site;

    private int $profileId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->organization = $this->makeOrg('O');
        $this->foreign = $this->makeOrg('X');
        $this->user = User::factory()->create();
        Membership::query()->create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);

        $client = Client::query()->create(['organization_id' => $this->organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $this->organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $this->site = Site::query()->create(['organization_id' => $this->organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
    }

    private function makeOrg(string $name): Organization
    {
        return Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => $name, 'slug' => strtolower($name).'-'.Str::random(5), 'status' => 'active']);
    }

    private function makeForeignSite(): Site
    {
        $client = Client::query()->create(['organization_id' => $this->foreign->id, 'public_id' => (string) Str::ulid(), 'name' => 'FC', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $this->foreign->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'FP', 'status' => 'active']);

        return Site::query()->create(['organization_id' => $this->foreign->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'F', 'canonical_url' => 'https://f.ir', 'status' => 'active']);
    }

    /** محتوای واقع‌بینانهٔ مقاله که از گیت کیفیت (کف ایمنی: ۱۵۰ کلمه + ۱ heading) عبور کند. */
    private function realisticPayload(): string
    {
        // جملهٔ پرکننده بدون کلیدواژه تا تراکم کلیدواژه زیر سقف بماند
        $filler = 'این مقاله شامل نکات کاربردی، مثال‌های واقعی و مراحل گام‌به‌گام برای بهبود عملکرد سایت شما است. ';
        $body = str_repeat($filler, 16); // ≈ ۲۴۰ کلمه
        $link = '<a href="https://example.com/guide">راهنمای سئو</a>';
        $content = '<p>فهرست مطالب: مقدمه، مراحل، سؤالات متداول، جمع‌بندی</p>'
            .'<p>اگر به دنبال راهنمای سئو هستید، این مقاله دقیقاً برای شما نوشته شده است. '.trim($body).' '.$link.'</p>'
            .'<h2>چرا این موضوع اهمیت دارد؟</h2><p>'.trim($body).' '.$link.'</p>'
            .'<h2>مراحل اجرا</h2><ol><li>گام اول: تحلیل</li><li>گام دوم: اجرا</li></ol><p>'.trim($body).' '.$link.'</p>'
            .'<h2>سؤالات متداول</h2><p><strong>پرسش:</strong> آیا سئو مهم است؟ <strong>پاسخ:</strong> بله.</p>'
            .'<p>در پایان همین راهنمای سئو را دنبال کنید و نتیجه را ببینید. همین حالا اقدام کنید.</p>';

        return json_encode([
            'title' => 'بهترین راهنمای سئو برای سایت شما',
            'content' => $content,
            'slug' => 'seo-guide',
            'target_query' => 'راهنمای سئو',
            'content_type' => 'article',
            'standard' => ['standard_key' => 'article\u00d7guide\u00d7informational', 'word_min' => 400, 'word_max' => 2000, 'required_elements' => ['faq', 'cta', 'internal_links', 'h2_structure', 'table_of_contents', 'steps'], 'min_headings' => 3],
            'profile' => ['content_type' => 'article', 'subtype' => 'guide', 'intent' => 'informational', 'title' => 'بهترین راهنمای سئو برای سایت شما'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, mixed> */
    private function articleCommand(Site $site, string $status = 'pending_approval', ?string $scheduledFor = null, ?int $profileId = null): array
    {
        $id = \DB::table('commands')->insertGetId([
            'site_id' => $site->id,
            'source_type' => 'ai_generation',
            'source_id' => random_int(1, 99999),
            'type' => 'publish_new_article',
            'content_type' => 'article',
            'risk_tier' => 'R3',
            'payload' => $this->realisticPayload(),
            'idempotency_key' => (string) Str::uuid(),
            'status' => $status,
            'confidence_score' => 92,
            'scheduled_for' => $scheduledFor,
            'expires_at' => now()->addDays(7),
            'policy_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'profile_id' => $profileId];
    }

    private function enableAutoPublishPolicy(): void
    {
        $this->profileId = \DB::table('automation_profiles')->insertGetId([
            'name' => 'Cal L3', 'slug' => 'cal-l3', 'kind' => 'custom', 'scope' => 'site',
            'automation_level' => 3, 'ai_policy' => 'bounded_auto', 'confidence_threshold' => 80,
            'high_risk_threshold' => 80, 'risk_tier_max' => 'R3', 'auto_rollback' => false,
            'enabled_content_types' => json_encode(['article'], JSON_UNESCAPED_UNICODE),
            'daily_command_limit' => 10, 'daily_mutation_limit' => 10, 'rollback_hours' => 336,
            'alert_level' => 'none', 'reviewer_policy' => 'one', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        \DB::table('site_automation_policies')->insert([
            'site_id' => $this->site->id, 'level' => 2,
            'rules' => json_encode(['allowed_command_types' => ['publish_new_article']], JSON_UNESCAPED_UNICODE),
            'active_profile_id' => $this->profileId, 'auto_publish_scope' => 'article',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // گرمایش: ۵ اجرای موفق انسانی از نوع article
        for ($i = 0; $i < 5; $i++) {
            \DB::table('commands')->insert([
                'site_id' => $this->site->id, 'source_type' => 'test', 'type' => 'publish_new_article',
                'content_type' => 'article', 'risk_tier' => 'R3', 'payload' => '{}',
                'idempotency_key' => (string) Str::uuid(), 'status' => 'executed',
                'decision_source' => 'manual', 'expires_at' => now()->addHours(2), 'policy_version' => 1,
                'created_at' => now()->subDays(60 + $i), 'updated_at' => now(),
            ]);
        }

        \DB::table('site_connections')->insert(['site_id' => $this->site->id, 'status' => 'connected', 'platform_url' => 'https://wp.test', 'secret_ciphertext' => Crypt::encryptString('secret'), 'created_at' => now(), 'updated_at' => now()]);
        Http::fake([
            '*/wp-json/vision-prime/v1/commands' => Http::response(['ok' => true, 'result' => ['post_id' => 1, 'previous' => [], 'new' => 'new']]),
        ]);
    }

    // ————— اکشن زمان‌بندی —————

    public function test_schedule_sets_scheduled_status_and_due_date(): void
    {
        $cmd = $this->articleCommand($this->site);
        $due = now()->addDays(3)->toDateTimeString();

        $result = app(SchedulePublish::class)->schedule($cmd['id'], $due);

        $this->assertSame('scheduled', $result['status']);
        $this->assertDatabaseHas('commands', ['id' => $cmd['id'], 'status' => 'scheduled']);
        $this->assertNotNull(\DB::table('commands')->where('id', $cmd['id'])->value('scheduled_for'));
    }

    public function test_cancel_returns_command_to_pending_approval(): void
    {
        $cmd = $this->articleCommand($this->site, status: 'scheduled', scheduledFor: now()->addDays(2)->toDateTimeString());

        $result = app(SchedulePublish::class)->cancel($cmd['id']);

        $this->assertSame('pending_approval', $result['status']);
        $this->assertDatabaseHas('commands', ['id' => $cmd['id'], 'status' => 'pending_approval']);
        $this->assertNull(\DB::table('commands')->where('id', $cmd['id'])->value('scheduled_for'));
    }

    public function test_schedule_rejects_non_article_command_and_past_date(): void
    {
        $metaId = \DB::table('commands')->insertGetId([
            'site_id' => $this->site->id, 'source_type' => 'test', 'type' => 'update_meta_title',
            'content_type' => 'meta', 'risk_tier' => 'R1', 'payload' => '{}',
            'idempotency_key' => (string) Str::uuid(), 'status' => 'pending_approval',
            'expires_at' => now()->addDays(7), 'policy_version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(HttpException::class);
        app(SchedulePublish::class)->schedule($metaId, now()->addDays(1)->toDateTimeString());

        $cmd = $this->articleCommand($this->site);
        try {
            app(SchedulePublish::class)->schedule($cmd['id'], now()->subDay()->toDateTimeString());
            $this->fail('باید با گذشته‌بودن موعد خطا بدهد.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    // ————— job آزادسازی —————

    public function test_release_job_publishes_due_scheduled_command(): void
    {
        $this->enableAutoPublishPolicy();
        $cmd = $this->articleCommand($this->site, status: 'scheduled', scheduledFor: now()->subMinute()->toDateTimeString(), profileId: $this->profileId);

        app(ReleaseScheduledCommands::class)->handle(app(AutoPublish::class));
        $this->assertDatabaseHas('commands', ['id' => $cmd['id'], 'status' => 'executed', 'decision_source' => 'policy']);
        $this->assertNotNull(\DB::table('commands')->where('id', $cmd['id'])->value('published_at'));
        // موعد به‌عنوان رکورد برنامه‌ریزی باقی می‌ماند
        $this->assertNotNull(\DB::table('commands')->where('id', $cmd['id'])->value('scheduled_for'));
    }

    public function test_release_job_keeps_future_command_scheduled(): void
    {
        $cmd = $this->articleCommand($this->site, status: 'scheduled', scheduledFor: now()->addDays(5)->toDateTimeString());

        app(ReleaseScheduledCommands::class)->handle(app(AutoPublish::class));

        $this->assertDatabaseHas('commands', ['id' => $cmd['id'], 'status' => 'scheduled']);
    }

    public function test_release_job_without_policy_returns_command_to_human_approval(): void
    {
        $cmd = $this->articleCommand($this->site, status: 'scheduled', scheduledFor: now()->subMinute()->toDateTimeString());

        app(ReleaseScheduledCommands::class)->handle(app(AutoPublish::class));

        // بدون policy خودکار، موعد رسیده → انتظار تأیید انسانی
        $this->assertDatabaseHas('commands', ['id' => $cmd['id'], 'status' => 'pending_approval']);
    }

    // ————— کنترلر —————

    public function test_index_requires_authentication(): void
    {
        $this->get('/app/content-calendar')->assertRedirect('/login');
    }

    public function test_index_lists_only_organization_commands(): void
    {
        $mine = $this->articleCommand($this->site, status: 'scheduled', scheduledFor: now()->addDay()->toDateTimeString());

        // سایت سازمان دیگر
        $foreignSite = $this->makeForeignSite();
        $this->articleCommand($foreignSite, status: 'scheduled', scheduledFor: now()->addDay()->toDateTimeString());

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->get('/app/content-calendar')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/ContentCalendar')
                ->has('items', 1)
                ->where('items.0.id', $mine['id'])
                ->has('itemsByDate'));
    }

    public function test_schedule_endpoint_sets_due_date(): void
    {
        $cmd = $this->articleCommand($this->site);
        $due = now()->addDays(2)->format('Y-m-d\TH:i');

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post("/app/content-calendar/commands/{$cmd['id']}/schedule", ['action' => 'schedule', 'scheduled_for' => $due])
            ->assertRedirect();

        $this->assertDatabaseHas('commands', ['id' => $cmd['id'], 'status' => 'scheduled']);
    }

    public function test_schedule_endpoint_rejects_foreign_command(): void
    {
        $foreignSite = $this->makeForeignSite();
        $foreignCmd = $this->articleCommand($foreignSite);

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post("/app/content-calendar/commands/{$foreignCmd['id']}/schedule", ['action' => 'schedule', 'scheduled_for' => now()->addDay()->format('Y-m-d\TH:i')])
            ->assertStatus(404);

        $this->assertDatabaseHas('commands', ['id' => $foreignCmd['id'], 'status' => 'pending_approval']);
    }

    // ————— انتشار فوری —————

    public function test_publish_now_releases_scheduled_command_through_autopublish(): void
    {
        $this->enableAutoPublishPolicy();
        $cmd = $this->articleCommand($this->site, status: 'scheduled', scheduledFor: now()->addDays(3)->toDateTimeString());

        $result = app(SchedulePublish::class)->publishNow($cmd['id'], app(AutoPublish::class));

        $this->assertSame('auto_publish', $result['decision']);
        $this->assertDatabaseHas('commands', ['id' => $cmd['id'], 'status' => 'executed', 'decision_source' => 'policy']);
        $this->assertNotNull(\DB::table('commands')->where('id', $cmd['id'])->value('published_at'));
    }

    public function test_schedule_endpoint_publish_now_action(): void
    {
        $cmd = $this->articleCommand($this->site, status: 'scheduled', scheduledFor: now()->addDays(3)->toDateTimeString());

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post("/app/content-calendar/commands/{$cmd['id']}/schedule", ['action' => 'publish_now'])
            ->assertRedirect();

        // بدون policy خودکار → پس از آزادسازی، در انتظار تأیید انسانی است (نه cancelled/executed)
        $this->assertDatabaseHas('commands', ['id' => $cmd['id'], 'status' => 'pending_approval']);
    }

    // ————— ساخت پیش‌نویس زمان‌بندی‌شده —————

    private function setUpProfile(): int
    {
        return \DB::table('url_profiles')->insertGetId([
            'site_id' => $this->site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://e.ir/blog/guide/',
            'content_type' => 'page',
            'post_status' => 'publish',
            'metadata' => json_encode(['gsc' => ['clicks' => 30, 'impressions' => 1200, 'ctr' => 0.025, 'position' => 11]], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_store_draft_creates_scheduled_generation(): void
    {
        $profileId = $this->setUpProfile();
        $due = now()->addDays(2)->format('Y-m-d\TH:i');

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post('/app/content-calendar/drafts', [
                'site_id' => $this->site->id,
                'url_profile_id' => $profileId,
                'title' => 'راهنمای کامل سئو',
                'scheduled_for' => $due,
            ])
            ->assertRedirect(route('app.reviews.index'));

        $generation = \DB::table('ai_generations')->where('site_id', $this->site->id)->first();
        $this->assertNotNull($generation);
        $this->assertNotNull($generation->scheduled_for);
        $this->assertSame(1, \DB::table('review_items')->where('subject_type', 'ai_generation')->where('subject_id', $generation->id)->count());
    }

    public function test_approving_generation_with_scheduled_for_schedules_command(): void
    {
        $profileId = $this->setUpProfile();
        $due = now()->addDays(2)->toDateTimeString();

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->post('/app/content-calendar/drafts', [
                'site_id' => $this->site->id,
                'url_profile_id' => $profileId,
                'title' => 'راهنمای کامل سئو',
                'scheduled_for' => now()->addDays(2)->format('Y-m-d\TH:i'),
            ]);

        $generation = \DB::table('ai_generations')->where('site_id', $this->site->id)->first();
        $review = \DB::table('review_items')->where('subject_type', 'ai_generation')->where('subject_id', $generation->id)->first();

        $result = app(DecideReviewItem::class)->handle((int) $review->id, $this->user, 'approved');

        $this->assertSame('approved', $result['status']);
        $command = \DB::table('commands')->where('source_type', 'ai_generation')->where('source_id', $generation->id)->first();
        $this->assertNotNull($command);
        $this->assertSame('scheduled', $command->status);
        $this->assertNotNull($command->scheduled_for);
    }

    // ————— پیشنهاد هوشمند روز انتشار —————

    public function test_suggest_publish_slot_uses_best_weekday_from_gsc(): void
    {
        $accountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $this->organization->id, 'google_subject' => 'acc', 'email' => 'acc@b.ir', 'token_ciphertext' => Crypt::encryptString('t'), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $propertyId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $accountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'sc-domain', 'status' => 'selected', 'created_at' => now(), 'updated_at' => now()]);

        // چهارشنبه‌ها (weekday 4) کلیک زیاد؛ بقیه کم
        foreach (range(0, 20) as $i) {
            $date = now()->subDays($i);
            $clicks = ((int) $date->format('w') + 1) % 7 === 4 ? 80 : 5;
            \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => $date->toDateString(), 'page_url' => 'https://e.ir/x', 'clicks' => $clicks, 'impressions' => 1000, 'ctr' => 0.05, 'position' => 8]);
        }

        $slot = app(SuggestPublishSlot::class)->suggest((int) $this->site->id);

        $this->assertNotNull($slot);
        $this->assertSame(4, $slot['weekday']);
        $this->assertSame('چهارشنبه', $slot['label']);
        $this->assertGreaterThan(now()->timestamp, strtotime($slot['datetime']));
    }

    public function test_suggest_publish_slot_returns_null_without_gsc_data(): void
    {
        $this->assertNull(app(SuggestPublishSlot::class)->suggest((int) $this->site->id));
    }

    public function test_suggest_publish_slot_uses_best_hour_from_hourly_gsc(): void
    {
        $accountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $this->organization->id, 'google_subject' => 'acc', 'email' => 'acc@b.ir', 'token_ciphertext' => Crypt::encryptString('t'), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $propertyId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $accountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'sc-domain', 'status' => 'selected', 'created_at' => now(), 'updated_at' => now()]);

        // روزهای پیاپی؛ ساعت ۲۰ کلیک زیاد، بقیه کم → بهترین ساعت = ۲۰
        foreach (range(0, 6) as $i) {
            $date = now()->subDays($i)->toDateString();
            foreach ([8, 12, 20] as $hour) {
                \DB::table('gsc_hourly_metrics')->insert([
                    'gsc_property_id' => $propertyId, 'date' => $date, 'hour' => $hour,
                    'clicks' => $hour === 20 ? 90 : 3, 'impressions' => 1000, 'ctr' => 0.05, 'position' => 8,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => now()->toDateString(), 'page_url' => 'https://e.ir/x', 'clicks' => 10, 'impressions' => 1000, 'ctr' => 0.01, 'position' => 8]);

        $slot = app(SuggestPublishSlot::class)->suggest((int) $this->site->id);

        $this->assertNotNull($slot);
        $this->assertSame('hourly', $slot['source']);
        $this->assertSame(20, $slot['hour']);
        $this->assertStringContainsString('20:00', $slot['datetime']);
    }

    public function test_gsc_hourly_upsert_stores_date_and_hour(): void
    {
        $accountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $this->organization->id, 'google_subject' => 'acc', 'email' => 'acc@b.ir', 'token_ciphertext' => Crypt::encryptString('t'), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $propertyId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $accountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'sc-domain', 'status' => 'selected', 'created_at' => now(), 'updated_at' => now()]);

        app(UpsertGscMetric::class)->hour($propertyId, '2026-08-01', ['keys' => ['2026-08-01', '14'], 'clicks' => 42, 'impressions' => 500, 'ctr' => 0.08, 'position' => 5]);

        $this->assertDatabaseHas('gsc_hourly_metrics', ['gsc_property_id' => $propertyId, 'date' => '2026-08-01', 'hour' => 14, 'clicks' => 42]);
    }

    // ————— یادآوری موعد انتشار —————

    public function test_reminder_notifies_active_members_one_day_before_due(): void
    {
        $cmd = $this->articleCommand($this->site, status: 'scheduled', scheduledFor: now()->addHours(20)->toDateTimeString());

        app(RemindScheduledPublishes::class)->handle();

        $this->assertSame(1, \DB::table('notifications')->count());
        $this->assertNotNull(\DB::table('commands')->where('id', $cmd['id'])->value('reminder_sent_at'));
    }

    public function test_reminder_skips_far_future_command_and_dedupes(): void
    {
        $far = $this->articleCommand($this->site, status: 'scheduled', scheduledFor: now()->addDays(5)->toDateTimeString());
        $due = $this->articleCommand($this->site, status: 'scheduled', scheduledFor: now()->addHours(20)->toDateTimeString());

        app(RemindScheduledPublishes::class)->handle();
        $this->assertSame(1, \DB::table('notifications')->count());
        $this->assertNull(\DB::table('commands')->where('id', $far['id'])->value('reminder_sent_at'));

        // اجرای دوباره → تکرار نمی‌شود (dedupe با reminder_sent_at)
        app(RemindScheduledPublishes::class)->handle();
        $this->assertSame(1, \DB::table('notifications')->count());
    }

    public function test_dashboard_passes_publish_suggestions(): void
    {
        $accountId = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $this->organization->id, 'google_subject' => 'acc', 'email' => 'acc@b.ir', 'token_ciphertext' => Crypt::encryptString('t'), 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $propertyId = \DB::table('gsc_properties')->insertGetId(['site_id' => $this->site->id, 'gsc_account_id' => $accountId, 'property_uri' => 'sc-domain:e.ir', 'property_type' => 'sc-domain', 'status' => 'selected', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('gsc_page_metrics')->insert(['gsc_property_id' => $propertyId, 'date' => now()->toDateString(), 'page_url' => 'https://e.ir/x', 'clicks' => 10, 'impressions' => 1000, 'ctr' => 0.01, 'position' => 8]);

        $this->actingAs($this->user)->withSession(['current_organization_id' => $this->organization->id])
            ->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('App/Dashboard')->has('publishSuggestions', 1));
    }
}
