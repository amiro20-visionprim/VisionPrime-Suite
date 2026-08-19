<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\ContentStandardsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticleDraftGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ContentStandardsSeeder::class);
    }

    private function setUpWorkspace(): array
    {
        $organization = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'R', 'slug' => 'r-'.Str::random(5), 'status' => 'active']);
        $admin = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'لیونا', 'canonical_url' => 'https://liuna.ir', 'status' => 'active']);
        $profileId = DB::table('url_profiles')->insertGetId([
            'site_id' => $site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://liuna.ir/blog/skincare-guide/',
            'content_type' => 'page',
            'post_status' => 'publish',
            'metadata' => json_encode(['gsc' => ['clicks' => 30, 'impressions' => 1200, 'ctr' => 0.025, 'position' => 11]], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keyword_insights')->insert([
            'site_id' => $site->id,
            'query_normalized' => 'راهنمای جامع مراقبت از پوست',
            'mapped_url_profile_id' => $profileId,
            'latest_metrics' => json_encode(['query' => 'راهنمای جامع مراقبت از پوست', 'impressions' => 1200], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$organization, $admin, $site, $profileId];
    }

    public function test_article_draft_is_generated_with_standard_and_profile_context(): void
    {
        [$organization, $admin, $site, $profileId] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts/article', ['url_profile_id' => $profileId])
            ->assertRedirect()
            ->assertSessionHas('status');

        $generation = DB::table('ai_generations')->where('site_id', $site->id)->first();
        $this->assertNotNull($generation);

        $version = DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = json_decode($version->output, true);

        $this->assertSame('article', $output['kind']);
        $this->assertSame('rule_based', $output['source']);
        // ContentProfiler: کوئری هدف «راهنمای جامع...» → زیرنوع pillar، قصد informational
        $this->assertSame('pillar', $output['profile']['subtype']);
        $this->assertSame('informational', $output['profile']['intent']);
        // استاندارد مؤثر از StandardsKB اعمال شده است
        $this->assertSame('article×pillar×informational', $output['standard']['standard_key']);
        $this->assertGreaterThanOrEqual(150, (int) $output['standard']['word_min']);

        // متن تولیدشده ساختار استاندارد دارد
        $text = (string) $output['text'];
        $this->assertStringContainsString('<h1>', $text);
        $this->assertStringContainsString('<h2>', $text);
        $plain = trim((string) preg_replace('/<[^>]+>/', ' ', $text));
        $this->assertGreaterThan(0, mb_strlen($plain, 'UTF-8'));

        // input_redacted شامل context جستجو (کوئری هدف + متریک GSC) است
        $input = json_decode($generation->input_redacted, true);
        $this->assertSame('راهنمای جامع مراقبت از پوست', $input['target_query']);
        $this->assertSame(30, $input['metrics']['clicks']);
        $this->assertSame(1200, $input['metrics']['impressions']);

        // وارد صف بازبینی انسانی شد
        $this->assertDatabaseHas('review_items', ['site_id' => $site->id, 'subject_type' => 'ai_generation', 'subject_id' => $generation->id, 'status' => 'pending_review']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.article_draft_generated']);
    }

    public function test_article_draft_with_custom_title_overrides_query(): void
    {
        [$organization, $admin, $site, $profileId] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts/article', ['url_profile_id' => $profileId, 'title' => 'بهترین روتین پوستی ۱۴۰۵']);

        $generation = DB::table('ai_generations')->where('site_id', $site->id)->first();
        $version = DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = json_decode($version->output, true);
        $input = json_decode($generation->input_redacted, true);

        $this->assertSame('بهترین روتین پوستی ۱۴۰۵', $output['profile']['title']);
        $this->assertSame('بهترین روتین پوستی ۱۴۰۵', $input['profile']['title']);
        // «بهترین» → زیرنوع listicle و قصد commercial از عنوان سفارشی تشخیص داده می‌شود
        $this->assertSame('listicle', $output['profile']['subtype']);
        $this->assertSame('commercial', $output['profile']['intent']);
    }

    public function test_article_draft_uses_configured_provider_when_set(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '<h1>مقاله کامل تولیدشده</h1><h2>بخش اول</h2><p>متن کامل مقاله برای بازبینی انسانی.</p>']]],
                'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 120],
            ], 200),
        ]);

        [$organization, $admin, $site, $profileId] = $this->setUpWorkspace();

        DB::table('ai_provider_settings')->insert([
            'organization_id' => $organization->id,
            'provider' => 'openai',
            'encrypted_config' => Crypt::encryptString(json_encode(['api_key' => 'sk-real', 'model' => 'gpt-4o-mini'])),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts/article', ['url_profile_id' => $profileId])
            ->assertRedirect();

        $generation = DB::table('ai_generations')->where('site_id', $site->id)->first();
        $version = DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = json_decode($version->output, true);

        $this->assertSame('ai', $output['source']);
        $this->assertSame('gpt-4o-mini', $output['model']);
        $this->assertStringContainsString('مقاله کامل تولیدشده', (string) $output['text']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.openai.com'));
    }

    public function test_article_draft_includes_featured_image_and_schema_markup(): void
    {
        [$organization, $admin, $site, $profileId] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts/article', ['url_profile_id' => $profileId])
            ->assertRedirect();

        $generation = DB::table('ai_generations')->where('site_id', $site->id)->first();
        $version = DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = json_decode($version->output, true);

        // تصویر شاخص پیشنهادی — بر اساس استاندارد مؤثر (pillar → ۱۶:۹ بزرگ)
        $this->assertArrayHasKey('featured_image', $output);
        $this->assertSame(1600, $output['featured_image']['suggested_width']);
        $this->assertSame(900, $output['featured_image']['suggested_height']);
        $this->assertSame('1600:900', $output['featured_image']['aspect']);
        $this->assertNotEmpty($output['featured_image']['alt']);
        $this->assertStringContainsString('زیرنوع', $output['featured_image']['rationale']);

        // اسکیمای Schema.org — Article همیشه؛ FAQPage چون استاندارد pillar عنصر faq را الزامی می‌کند
        $this->assertIsArray($output['schema']);
        $types = array_column($output['schema'], '@type');
        $this->assertContains('Article', $types);
        $this->assertContains('FAQPage', $types);

        $article = $output['schema'][0];
        $this->assertSame('https://schema.org', $article['@context']);
        $this->assertSame('راهنمای جامع مراقبت از پوست', $article['headline']);
        $this->assertSame('https://liuna.ir/blog/skincare-guide/', $article['mainEntityOfPage']['@id']);
        $this->assertSame('لیونا', $article['publisher']['name']);

        // FAQPage با پرسش‌های استخراج‌شده از محتوای مقاله
        $faq = $output['schema'][1];
        $this->assertSame('FAQPage', $faq['@type']);
        $this->assertGreaterThanOrEqual(2, count($faq['mainEntity']));
        $this->assertSame('Question', $faq['mainEntity'][0]['@type']);
        $this->assertNotEmpty($faq['mainEntity'][0]['name']);
        $this->assertNotEmpty($faq['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function test_article_draft_schema_omits_faq_when_not_required(): void
    {
        [$organization, $admin, $site, $profileId] = $this->setUpWorkspace();

        // زیرنوعی که عنصر faq را الزامی نمی‌کند — مثلاً «بررسی/نقد» (review) با عنوان سفارشی
        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts/article', ['url_profile_id' => $profileId, 'title' => 'بررسی و نقد محصول'])
            ->assertRedirect();

        $generation = DB::table('ai_generations')->where('site_id', $site->id)->first();
        $version = DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = json_decode($version->output, true);

        $this->assertSame('review', $output['profile']['subtype']);
        $types = array_column($output['schema'], '@type');
        $this->assertContains('Article', $types);
        $this->assertNotContains('FAQPage', $types);
    }

    public function test_product_draft_generates_product_schema_and_square_featured_image(): void
    {
        [$organization, $admin, $site, $profileId] = $this->setUpWorkspace();

        // پروفایل محصول (ووکامرس)
        $productProfileId = DB::table('url_profiles')->insertGetId([
            'site_id' => $site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://liuna.ir/product/serum-c/',
            'content_type' => 'product',
            'post_status' => 'publish',
            'metadata' => json_encode(['gsc' => ['clicks' => 5, 'impressions' => 300, 'ctr' => 0.016, 'position' => 18]], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts/article', ['url_profile_id' => $productProfileId, 'title' => 'بهترین سرم ویتامین C اصل']);
        if ($response->isRedirect() && session('error') !== null) {
            $this->fail('Generation error: '.session('error'));
        }
        $response->assertRedirect();

        $generation = DB::table('ai_generations')->where('site_id', $site->id)->orderByDesc('id')->first();
        $version = DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = json_decode($version->output, true);

        // نوع پیش‌نویس product + اسکیمای Product
        $this->assertSame('product', $output['kind']);
        $this->assertSame('product', $output['profile']['content_type']);
        $this->assertSame('short_desc', $output['profile']['subtype']);
        $this->assertSame('product×short_desc×commercial', $output['standard']['standard_key']);

        $types = array_column($output['schema'], '@type');
        $this->assertContains('Product', $types);
        $this->assertNotContains('Article', $types);
        $product = $output['schema'][0];
        $this->assertSame('بهترین سرم ویتامین C اصل', $product['name']);
        $this->assertSame('https://liuna.ir/product/serum-c/', $product['mainEntityOfPage']['@id']);
        $this->assertSame('لیونا', $product['brand']['name']);
        $this->assertNotEmpty($product['description']);

        // تصویر شاخص مربعی (استاندارد گالری ووکامرس)
        $this->assertSame(1200, $output['featured_image']['suggested_width']);
        $this->assertSame(1200, $output['featured_image']['suggested_height']);
        $this->assertSame('1200:1200', $output['featured_image']['aspect']);
    }

    public function test_article_draft_for_foreign_url_profile_is_rejected(): void
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

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts/article', ['url_profile_id' => $foreignProfileId])
            ->assertNotFound();

        $this->assertDatabaseCount('ai_generations', 0);
    }
}
