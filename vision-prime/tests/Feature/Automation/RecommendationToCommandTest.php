<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Seo\Models\Recommendation;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RecommendationToCommandTest extends TestCase
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
        $user = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);
        $profileId = DB::table('url_profiles')->insertGetId([
            'site_id' => $site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://example.ir/services',
            'content_type' => 'page',
            'post_status' => 'publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$organization, $user, $site, $profileId];
    }

    public function test_recommendation_can_be_converted_to_command_for_client_approval(): void
    {
        [$organization, $user, $site, $profileId] = $this->setUpWorkspace();

        $opportunityId = DB::table('opportunities')->insertGetId([
            'site_id' => $site->id,
            'url_profile_id' => $profileId,
            'type' => 'ctr_gap',
            'score' => 88.0,
            'confidence' => 0.9,
            'status' => 'open',
            'explanation' => 'شکاف نرخ کلیک قابل توجه برای «خدمات سئو»',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recommendation = Recommendation::query()->create([
            'site_id' => $site->id,
            'source_type' => 'opportunity',
            'source_id' => $opportunityId,
            'title' => 'بهبود عنوان متا صفحه خدمات',
            'body' => 'عنوان متا جذاب‌تر بنویسید.',
            'priority' => 'high',
            'status' => 'active',
        ]);

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/recommendations/{$recommendation->id}/command", [
                'type' => 'update_meta_title',
                'target_url' => 'https://example.ir/services',
                'new_value' => 'خدمات سئو حرفه‌ای | Vision Prime',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $command = DB::table('commands')
            ->where('source_type', 'recommendation')
            ->where('source_id', $recommendation->id)
            ->first();

        $this->assertNotNull($command);
        $this->assertSame('pending_approval', $command->status);
        $this->assertSame('R3', $command->risk_tier);
        $this->assertSame('update_meta_title', $command->type);
        $this->assertSame($site->id, $command->site_id);
        $this->assertSame(
            ['url' => 'https://example.ir/services', 'title' => 'خدمات سئو حرفه‌ای | Vision Prime'],
            json_decode($command->payload, true),
        );
        $this->assertTrue(now()->lt($command->expires_at));
        $this->assertDatabaseHas('audit_logs', ['action' => 'command.created_from_recommendation']);
    }

    public function test_converting_twice_is_idempotent(): void
    {
        [$organization, $user, $site] = $this->setUpWorkspace();

        $recommendation = Recommendation::query()->create([
            'site_id' => $site->id,
            'source_type' => 'manual',
            'title' => 'بهبود توضیحات متا',
            'body' => '',
            'priority' => 'medium',
            'status' => 'draft',
        ]);

        $payload = [
            'type' => 'update_meta_description',
            'target_url' => 'https://example.ir/services',
            'new_value' => 'توضیحات متا جدید',
        ];

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/recommendations/{$recommendation->id}/command", $payload)
            ->assertRedirect();

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/recommendations/{$recommendation->id}/command", $payload)
            ->assertRedirect();

        $this->assertSame(1, DB::table('commands')->where('source_id', $recommendation->id)->count());
        // A draft recommendation becomes active once converted.
        $this->assertSame('active', $recommendation->fresh()->status);
    }

    public function test_cannot_convert_recommendation_of_another_organization(): void
    {
        [$organization, $user] = $this->setUpWorkspace();

        $foreignOrg = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'F', 'slug' => 'f-'.Str::random(5), 'status' => 'active']);
        $foreignClient = Client::query()->create(['organization_id' => $foreignOrg->id, 'public_id' => (string) Str::ulid(), 'name' => 'FC', 'status' => 'active']);
        $foreignProject = Project::query()->create(['organization_id' => $foreignOrg->id, 'client_id' => $foreignClient->id, 'public_id' => (string) Str::ulid(), 'name' => 'FP', 'status' => 'active']);
        $foreignSite = Site::query()->create(['organization_id' => $foreignOrg->id, 'project_id' => $foreignProject->id, 'public_id' => (string) Str::ulid(), 'name' => 'FS', 'canonical_url' => 'https://foreign.ir', 'status' => 'active']);
        $recommendation = Recommendation::query()->create([
            'site_id' => $foreignSite->id,
            'source_type' => 'manual',
            'title' => 'نفوذ',
            'body' => '',
            'priority' => 'low',
            'status' => 'active',
        ]);

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/recommendations/{$recommendation->id}/command", [
                'type' => 'update_meta_title',
                'target_url' => 'https://foreign.ir/',
                'new_value' => 'x',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('commands', ['source_id' => $recommendation->id]);
    }

    public function test_unsupported_command_type_is_rejected(): void
    {
        [$organization, $user, $site] = $this->setUpWorkspace();

        $recommendation = Recommendation::query()->create([
            'site_id' => $site->id,
            'source_type' => 'manual',
            'title' => 'پیشنهاد',
            'body' => '',
            'priority' => 'low',
            'status' => 'active',
        ]);

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/recommendations/{$recommendation->id}/command", [
                'type' => 'update_schema',
                'target_url' => 'https://example.ir/',
                'new_value' => 'x',
            ])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseMissing('commands', ['source_id' => $recommendation->id]);
    }

    public function test_recommendation_can_be_converted_to_content_command(): void
    {
        [$organization, $user, $site] = $this->setUpWorkspace();

        $recommendation = Recommendation::query()->create([
            'site_id' => $site->id,
            'source_type' => 'manual',
            'title' => 'بازنویسی محتوای صفحه خدمات',
            'body' => 'محتوای عمیق‌تر با CTA واضح.',
            'priority' => 'high',
            'status' => 'active',
        ]);

        $content = '<h2>بخش جدید</h2><p>محتوای جایگزین صفحه برای تست جریان محتوایی.</p>';

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/recommendations/{$recommendation->id}/command", [
                'type' => 'update_content',
                'target_url' => 'https://example.ir/services',
                'new_value' => $content,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $command = DB::table('commands')
            ->where('source_type', 'recommendation')
            ->where('source_id', $recommendation->id)
            ->first();

        $this->assertNotNull($command);
        $this->assertSame('update_content', $command->type);
        $this->assertSame('pending_approval', $command->status);
        $this->assertSame(
            ['url' => 'https://example.ir/services', 'content' => $content],
            json_decode($command->payload, true),
        );
    }

    public function test_content_command_accepts_long_content_but_meta_is_length_limited(): void
    {
        [$organization, $user, $site] = $this->setUpWorkspace();

        $long = str_repeat('م', 3000);
        $recommendation = Recommendation::query()->create([
            'site_id' => $site->id,
            'source_type' => 'manual',
            'title' => 'P',
            'body' => '',
            'priority' => 'low',
            'status' => 'active',
        ]);

        // محتوای بلند (۳۰۰۰ کاراکتر) برای update_content مجاز است.
        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/recommendations/{$recommendation->id}/command", [
                'type' => 'update_content',
                'target_url' => 'https://example.ir/services',
                'new_value' => $long,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        // اما همان مقدار برای update_meta_title رد می‌شود.
        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->post("/app/recommendations/{$recommendation->id}/command", [
                'type' => 'update_meta_title',
                'target_url' => 'https://example.ir/services',
                'new_value' => $long,
            ])
            ->assertSessionHasErrors('new_value');
    }

    public function test_index_exposes_target_url_and_existing_command(): void
    {
        [$organization, $user, $site, $profileId] = $this->setUpWorkspace();

        $opportunityId = DB::table('opportunities')->insertGetId([
            'site_id' => $site->id,
            'url_profile_id' => $profileId,
            'type' => 'ctr_gap',
            'score' => 70.0,
            'confidence' => 0.7,
            'status' => 'open',
            'explanation' => 'فرصت',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recommendation = Recommendation::query()->create([
            'site_id' => $site->id,
            'source_type' => 'opportunity',
            'source_id' => $opportunityId,
            'title' => 'Title',
            'body' => '',
            'priority' => 'medium',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/recommendations')
            ->assertOk();

        $payload = $response->viewData('page')['props']['recommendations']['data'][0] ?? null;
        $this->assertNotNull($payload);
        $this->assertSame('https://example.ir/services', $payload['targetUrl']);
        $this->assertNull($payload['commandId']);
    }
}
