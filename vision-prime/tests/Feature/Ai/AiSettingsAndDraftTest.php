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
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiSettingsAndDraftTest extends TestCase
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
        $member = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $admin->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $member->id, 'role_id' => Role::query()->where('key', 'client-viewer')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'لیونا', 'canonical_url' => 'https://liuna.ir', 'status' => 'active']);
        $profileId = DB::table('url_profiles')->insertGetId([
            'site_id' => $site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://liuna.ir/shop/skincare/serum/',
            'content_type' => 'product',
            'post_status' => 'publish',
            'metadata' => json_encode(['gsc' => ['clicks' => 12, 'impressions' => 900, 'ctr' => 0.0133, 'position' => 18]], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('keyword_insights')->insert([
            'site_id' => $site->id,
            'query_normalized' => 'سرم پوست',
            'mapped_url_profile_id' => $profileId,
            'latest_metrics' => json_encode(['query' => 'سرم پوست', 'impressions' => 900]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$organization, $admin, $member, $site, $profileId];
    }

    public function test_admin_can_configure_ai_provider_with_encrypted_key(): void
    {
        [$organization, $admin] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/settings/ai-provider', [
                'provider' => 'openai',
                'api_key' => 'sk-secret-123',
                'model' => 'gpt-4o-mini',
            ])
            ->assertRedirect();

        $setting = DB::table('ai_provider_settings')->where('organization_id', $organization->id)->first();
        $this->assertNotNull($setting);
        $this->assertSame('openai', $setting->provider);
        $this->assertSame('active', $setting->status);
        $this->assertSame(['api_key' => 'sk-secret-123', 'model' => 'gpt-4o-mini'], json_decode(Crypt::decryptString($setting->encrypted_config), true));
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.provider_setting_saved']);
    }

    public function test_member_without_manage_permission_cannot_configure_provider(): void
    {
        [$organization, , $member] = $this->setUpWorkspace();

        $this->actingAs($member)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/settings/ai-provider', [
                'provider' => 'openai',
                'api_key' => 'sk-secret-123',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('ai_provider_settings', ['organization_id' => $organization->id]);
    }

    public function test_provider_setting_can_be_removed(): void
    {
        [$organization, $admin] = $this->setUpWorkspace();

        DB::table('ai_provider_settings')->insert([
            'organization_id' => $organization->id,
            'provider' => 'openrouter',
            'encrypted_config' => Crypt::encryptString(json_encode(['api_key' => 'x'])),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->delete('/app/settings/ai-provider/openrouter')
            ->assertRedirect();

        $this->assertDatabaseMissing('ai_provider_settings', ['organization_id' => $organization->id, 'provider' => 'openrouter']);
    }

    public function test_draft_generation_uses_rule_based_fallback_without_provider(): void
    {
        [$organization, $admin, , $site, $profileId] = $this->setUpWorkspace();

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts', ['url_profile_id' => $profileId, 'kind' => 'meta_title'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $generation = DB::table('ai_generations')->where('site_id', $site->id)->first();
        $this->assertNotNull($generation);
        $version = DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = json_decode($version->output, true);
        $this->assertSame('meta_title', $output['kind']);
        $this->assertSame('rule_based', $output['source']);
        $this->assertStringContainsString('سرم پوست', (string) $output['text']);
        $this->assertDatabaseHas('review_items', ['site_id' => $site->id, 'subject_type' => 'ai_generation', 'subject_id' => $generation->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.draft_generated']);
    }

    public function test_draft_generation_uses_configured_provider(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'عنوان پیشنهادی هوشمند']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ], 200),
        ]);

        [$organization, $admin, , $site, $profileId] = $this->setUpWorkspace();

        DB::table('ai_provider_settings')->insert([
            'organization_id' => $organization->id,
            'provider' => 'openai',
            'encrypted_config' => Crypt::encryptString(json_encode(['api_key' => 'sk-real', 'model' => 'gpt-4o-mini'])),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->withSession(['current_organization_id' => $organization->id])
            ->post('/app/ai-drafts', ['url_profile_id' => $profileId, 'kind' => 'meta_description'])
            ->assertRedirect();

        $generation = DB::table('ai_generations')->where('site_id', $site->id)->first();
        $this->assertNotNull($generation);
        $version = DB::table('ai_generation_versions')->where('id', $generation->current_version_id)->first();
        $output = json_decode($version->output, true);
        $this->assertSame('ai', $output['source']);
        $this->assertSame('عنوان پیشنهادی هوشمند', $output['text']);
        $this->assertSame(15, json_decode($generation->usage, true)['input_tokens'] + json_decode($generation->usage, true)['output_tokens']);
    }

    public function test_draft_for_foreign_url_profile_is_rejected(): void
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
            ->post('/app/ai-drafts', ['url_profile_id' => $foreignProfileId, 'kind' => 'meta_title'])
            ->assertNotFound();

        $this->assertDatabaseCount('ai_generations', 0);
    }
}
