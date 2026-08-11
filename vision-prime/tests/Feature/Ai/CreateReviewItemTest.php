<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domains\Ai\Actions\CreateReviewItem;
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
use Tests\TestCase;

class CreateReviewItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function setUpSite(): array
    {
        $organization = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'R', 'slug' => 'r-'.Str::random(5), 'status' => 'active']);
        $user = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);

        return [$organization, $user, $site];
    }

    public function test_creates_pending_review_item_with_due_date_and_audit_log(): void
    {
        [, , $site] = $this->setUpSite();
        $profileId = DB::table('url_profiles')->insertGetId([
            'site_id' => $site->id,
            'public_id' => (string) Str::ulid(),
            'canonical_url' => 'https://e.ir/shop/item/',
            'content_type' => 'product',
            'post_status' => 'publish',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $auditId = DB::table('money_page_audits')->insertGetId([
            'url_profile_id' => $profileId,
            'score' => 55.0,
            'summary' => '{}',
            'audited_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = app(CreateReviewItem::class)->handle($site, 'money_page_audit', $auditId);

        $this->assertDatabaseHas('review_items', [
            'id' => $id,
            'site_id' => $site->id,
            'subject_type' => 'money_page_audit',
            'subject_id' => $auditId,
            'status' => 'pending_review',
        ]);
        $this->assertTrue(now()->addDays(3)->gte(DB::table('review_items')->where('id', $id)->value('due_at')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'review.item_created']);
    }

    public function test_is_idempotent_per_subject(): void
    {
        [, , $site] = $this->setUpSite();
        $generationId = DB::table('ai_generations')->insertGetId([
            'site_id' => $site->id,
            'output_status' => 'needs_review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $first = app(CreateReviewItem::class)->handle($site, 'ai_generation', $generationId);
        $second = app(CreateReviewItem::class)->handle($site, 'ai_generation', $generationId);

        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('review_items')->where('site_id', $site->id)->count());
    }
}
