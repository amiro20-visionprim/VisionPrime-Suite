<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function setUpWorkspace(): array
    {
        $organization = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'D', 'slug' => 'd-'.Str::random(5), 'status' => 'active']);
        $user = User::factory()->create();
        Membership::query()->create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $organization->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $organization->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $organization->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);

        return [$organization, $user, $site];
    }

    public function test_dashboard_renders_with_real_counts_and_activities(): void
    {
        [$organization, $user, $site] = $this->setUpWorkspace();

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/DashboardPlaceholder')
                ->where('counts.clients', 1)
                ->where('counts.projects', 1)
                ->where('counts.sites', 1)
                ->where('counts.connectedSites', 0)
                ->where('counts.openOpportunities', 0)
                ->has('activities'));
    }

    public function test_dashboard_counts_open_opportunities_and_connected_sites(): void
    {
        [$organization, $user, $site] = $this->setUpWorkspace();

        DB::table('opportunities')->insert([
            'site_id' => $site->id,
            'type' => 'ctr_gap',
            'score' => 82.5,
            'confidence' => 0.87,
            'status' => 'open',
            'explanation' => 'شکاف نرخ کلیک بالا',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('site_connections')->insert([
            'site_id' => $site->id,
            'status' => 'connected',
            'platform_url' => 'https://example.ir',
            'plugin_version' => '0.1.0',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->withSession(['current_organization_id' => $organization->id])
            ->get('/app/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.openOpportunities', 1)
                ->where('counts.connectedSites', 1));
    }
}
