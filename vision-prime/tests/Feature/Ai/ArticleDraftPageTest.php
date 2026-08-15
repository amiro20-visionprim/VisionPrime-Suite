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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ArticleDraftPageTest extends TestCase
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
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'لیونا', 'canonical_url' => 'https://liuna.ir', 'status' => 'active']);
        $profileId = DB::table('url_profiles')->insertGetId([
            'site_id' => $site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://liuna.ir/blog/seo-guide/',
            'content_type' => 'page',
            'post_status' => 'publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keyword_insights')->insert([
            'site_id' => $site->id,
            'query_normalized' => 'آموزش سئو',
            'mapped_url_profile_id' => $profileId,
            'latest_metrics' => json_encode(['query' => 'آموزش سئو'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$organization, $admin, $site, $profileId];
    }

    public function test_create_page_renders_sites_profiles_and_subtypes(): void
    {
        [$organization, $admin] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/ai-drafts/article/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('App/ArticleDraft/Create')
                ->has('sites', 1)
                ->has('profiles', 1)
                ->where('subtypes.tutorial', 'آموزشی')
                ->where('subtypes.comparison', 'مقایسه‌ای')
                ->where('subtypes.pillar', 'راهنمای جامع (پیلار)'));
    }

    public function test_manual_subtype_is_respected_in_generated_draft(): void
    {
        [$organization, $admin, $site, $profileId] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts/article', [
                'url_profile_id' => $profileId,
                'title' => 'بهترین ابزارهای سئو',
                'subtype' => 'comparison',
            ])
            ->assertRedirect(route('app.reviews.index'));

        $generation = DB::table('ai_generations')->where('site_id', $site->id)->first();
        $this->assertNotNull($generation);
        $version = DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = json_decode($version->output, true);

        // انتخاب دستی زیرنوع بر تشخیص خودکار اولویت دارد («بهترین» → listicle ولی ما comparison خواستیم)
        $this->assertSame('comparison', $output['profile']['subtype']);
        // استاندارد مؤثر مقایسه‌ای اعمال شده است
        $this->assertSame('article×comparison×commercial', $output['standard']['standard_key']);

        $this->assertDatabaseHas('review_items', ['site_id' => $site->id, 'subject_type' => 'ai_generation', 'subject_id' => $generation->id]);
    }

    public function test_product_create_page_renders_only_product_profiles_and_subtypes(): void
    {
        [$organization, $admin, $site] = $this->setUpWorkspace();
        DB::table('url_profiles')->insert([
            'site_id' => $site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://liuna.ir/product/serum-c/',
            'content_type' => 'product',
            'post_status' => 'publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/ai-drafts/product/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('App/ProductDraft/Create')
                ->has('sites', 1)
                ->where('profiles.0.content_type', 'product')
                ->where('subtypes.short_desc', 'توضیح کوتاه محصول')
                ->where('subtypes.long_desc', 'توضیح بلند محصول')
                ->where('subtypes.technical', 'مشخصات فنی')
                ->where('subtypes.comparison', 'مقایسه‌ای')
                ->missing('subtypes.pillar'));
    }

    public function test_product_draft_page_generates_product_draft(): void
    {
        [$organization, $admin, $site] = $this->setUpWorkspace();
        $productProfileId = DB::table('url_profiles')->insertGetId([
            'site_id' => $site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://liuna.ir/product/serum-c/',
            'content_type' => 'product',
            'post_status' => 'publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts/product', [
                'url_profile_id' => $productProfileId,
                'title' => 'بهترین سرم ویتامین C',
                'subtype' => 'long_desc',
            ])
            ->assertRedirect(route('app.reviews.index'));

        $generation = DB::table('ai_generations')->where('site_id', $site->id)->first();
        $this->assertNotNull($generation);
        $version = DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = json_decode($version->output, true);
        $this->assertSame('product', $output['kind']);
        $this->assertSame('long_desc', $output['profile']['subtype']);
        $this->assertSame('product', $output['profile']['content_type']);
    }

    public function test_product_draft_rejects_non_product_profile(): void
    {
        [$organization, $admin, $site, $profileId] = $this->setUpWorkspace();

        // profileId یک صفحهٔ عادی است — نباید از مسیر محصول قابل استفاده باشد
        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts/product', ['url_profile_id' => $profileId])
            ->assertNotFound();

        $this->assertDatabaseCount('ai_generations', 0);
    }

    public function test_invalid_subtype_is_rejected(): void
    {
        [$organization, $admin, $site, $profileId] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts/article', [
                'url_profile_id' => $profileId,
                'subtype' => 'not-a-real-subtype',
            ])
            ->assertSessionHasErrors('subtype');

        $this->assertDatabaseCount('ai_generations', 0);
    }

    public function test_review_detail_returns_safe_html_for_article(): void
    {
        [$organization, $admin, $site, $profileId] = $this->setUpWorkspace();

        // یک پیشنویس مقاله با محتوای شامل تگ خطرناک می‌سازیم
        $genId = DB::table('ai_generations')->insertGetId([
            'site_id' => $site->id,
            'template_id' => null,
            'input_redacted' => json_encode(['kind' => 'article', 'url' => 'https://liuna.ir/x']),
            'output_status' => 'needs_review',
            'usage' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $versionId = DB::table('ai_generation_versions')->insertGetId([
            'generation_id' => $genId,
            'version' => 1,
            'output' => json_encode([
                'kind' => 'article',
                'text' => '<h1>عنوان</h1><script>alert(1)</script><h2>مقدمه</h2><p>متن مقاله</p>',
                'model' => 'rule-based',
                'source' => 'rule_based',
                'standard' => ['required_elements' => ['h2_structure', 'cta'], 'standard_key' => 'article×tutorial×informational'],
            ]),
            'status' => 'needs_review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_generations')->where('id', $genId)->update(['current_version_id' => $versionId]);
        $reviewId = DB::table('review_items')->insertGetId([
            'site_id' => $site->id,
            'subject_type' => 'ai_generation',
            'subject_id' => $genId,
            'status' => 'pending_review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/reviews/'.$reviewId)
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('subject.generation.kind', 'article')
                ->where('subject.generation.safe_html', fn (string $html): bool => str_contains($html, '<h1>عنوان</h1>') && ! str_contains($html, '<script'))
                ->where('subject.generation.structure.headings.0.text', 'عنوان')
                ->where('subject.generation.structure.elements.h2_structure', true));
    }
}
