<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domains\Content\Jobs\SyncSiteContent;
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
use Tests\TestCase;

class SyncJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_syncs_site_content_end_to_end(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $org = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'آژانس', 'slug' => 'ag-'.Str::random(6), 'status' => 'active']);
        $user = User::factory()->create(['email' => 'admin@test.ir']);
        Membership::query()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $org->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $org->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $org->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);

        \DB::table('site_connections')->insert([
            'site_id' => $site->id,
            'status' => 'connected',
            'platform_url' => 'https://wp.test',
            'secret_ciphertext' => Crypt::encryptString('test-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'https://wp.test/wp-json/vision-prime/v1/content*' => Http::response([
                'data' => [[
                    'id' => 10, 'url' => 'https://example.ir/service', 'slug' => 'service',
                    'type' => 'page', 'status' => 'publish', 'title' => 'خدمات',
                    'meta_title' => 'متا', 'meta_description' => 'توضیح',
                    'headings' => ['خدمات'], 'word_count' => 42,
                    'content_hash' => 'hash-1', 'content' => '<p>محتوا</p>',
                ]],
                'total_pages' => 1,
            ]),
        ]);

        // Journey: admin clicks "شروع همگامسازی" on the sync page.
        $this->actingAs($user)
            ->post("/app/sites/{$site->id}/sync")
            ->assertRedirect();

        // The queue is sync in tests, so the job ran inline and finished.
        $this->assertDatabaseHas('sync_runs', ['site_id' => $site->id, 'status' => 'completed']);
        $this->assertDatabaseHas('url_profiles', ['site_id' => $site->id, 'canonical_url' => 'https://example.ir/service']);

        // The sync status page shows the finished run to the user.
        $this->actingAs($user)
            ->get("/app/sites/{$site->id}/sync")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/Sites/Sync')
                ->where('run.status', 'completed')
                ->where('run.summary.items', 1));
    }

    public function test_sync_failure_is_surfaced_on_status_page(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $org = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'آژانس', 'slug' => 'ag-'.Str::random(6), 'status' => 'active']);
        $user = User::factory()->create(['email' => 'admin@test.ir']);
        Membership::query()->create(['organization_id' => $org->id, 'user_id' => $user->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $org->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $org->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $org->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);

        \DB::table('site_connections')->insert([
            'site_id' => $site->id,
            'status' => 'connected',
            'platform_url' => 'https://wp.test',
            'secret_ciphertext' => Crypt::encryptString('test-secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake(['https://wp.test/*' => Http::response('boom', 500)]);

        \Illuminate\Support\Facades\Queue::fake();
        $this->actingAs($user)
            ->post("/app/sites/{$site->id}/sync")
            ->assertRedirect();

        // Simulate the queue worker: the job catches the failure, marks the run
        // failed, and rethrows (which the worker handles as a failed job).
        $runId = (int) \DB::table('sync_runs')->where('site_id', $site->id)->value('id');
        try {
            app()->call([new SyncSiteContent($runId), 'handle']);
        } catch (\Throwable $e) {
            // expected: connector unreachable
        }

        $this->assertDatabaseHas('sync_runs', ['site_id' => $site->id, 'status' => 'failed']);
        $this->actingAs($user)
            ->get("/app/sites/{$site->id}/sync")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/Sites/Sync')
                ->where('run.status', 'failed'));
    }
}
