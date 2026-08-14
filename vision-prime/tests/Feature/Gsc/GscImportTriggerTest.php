<?php

declare(strict_types=1);

namespace Tests\Feature\Gsc;

use App\Domains\Gsc\Jobs\ImportGscMetrics;
use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class GscImportTriggerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private Site $site;

    private int $propertyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->organization = Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'سازمان آزمون',
            'slug' => 'org-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['name' => 'مدیر', 'email' => 'admin@test.ir']);
        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $this->admin->getKey(),
            'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'),
            'status' => 'active',
        ]);

        $client = Client::query()->create([
            'organization_id' => $this->organization->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'مشتری',
            'status' => 'active',
        ]);
        $project = Project::query()->create([
            'organization_id' => $this->organization->getKey(),
            'client_id' => $client->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'پروژه',
            'status' => 'active',
        ]);
        $this->site = Site::query()->create([
            'organization_id' => $this->organization->getKey(),
            'project_id' => $project->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'سایت',
            'canonical_url' => 'https://example.ir',
            'status' => 'active',
        ]);

        $accountId = \DB::table('gsc_accounts')->insertGetId([
            'organization_id' => $this->organization->getKey(),
            'google_subject' => 'sub-1',
            'email' => 'gsc@test.ir',
            'token_ciphertext' => 'x',
            'status' => 'connected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->propertyId = \DB::table('gsc_properties')->insertGetId([
            'site_id' => $this->site->getKey(),
            'gsc_account_id' => $accountId,
            'property_uri' => 'https://example.ir',
            'property_type' => 'url-prefix',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_can_dispatch_import_for_owned_property(): void
    {
        Queue::fake();

        $this->actingAs($this->admin)
            ->post('/app/gsc/import', [
                'gsc_property_id' => $this->propertyId,
                'date_start' => now()->subDays(27)->toDateString(),
                'date_end' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('gsc_import_runs', [
            'gsc_property_id' => $this->propertyId,
            'status' => 'queued',
        ]);
        Queue::assertPushed(ImportGscMetrics::class);
    }

    public function test_import_for_another_organizations_property_is_rejected(): void
    {
        $other = Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'سازمان دیگر',
            'slug' => 'org-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
        $otherClient = Client::query()->create([
            'organization_id' => $other->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'مشتری دیگر',
            'status' => 'active',
        ]);
        $otherProject = Project::query()->create([
            'organization_id' => $other->getKey(),
            'client_id' => $otherClient->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'پروژه دیگر',
            'status' => 'active',
        ]);
        $otherSite = Site::query()->create([
            'organization_id' => $other->getKey(),
            'project_id' => $otherProject->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'سایت دیگر',
            'canonical_url' => 'https://other.ir',
            'status' => 'active',
        ]);
        $otherAccount = \DB::table('gsc_accounts')->insertGetId([
            'organization_id' => $other->getKey(),
            'google_subject' => 'sub-2',
            'email' => 'other@test.ir',
            'token_ciphertext' => 'x',
            'status' => 'connected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherProperty = \DB::table('gsc_properties')->insertGetId([
            'site_id' => $otherSite->getKey(),
            'gsc_account_id' => $otherAccount,
            'property_uri' => 'https://other.ir',
            'property_type' => 'url-prefix',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post('/app/gsc/import', [
                'gsc_property_id' => $otherProperty,
                'date_start' => now()->subDays(7)->toDateString(),
                'date_end' => now()->toDateString(),
            ])
            ->assertNotFound();

        $this->assertSame(0, \DB::table('gsc_import_runs')->count());
    }

    public function test_import_requires_valid_date_range(): void
    {
        $this->actingAs($this->admin)
            ->post('/app/gsc/import', [
                'gsc_property_id' => $this->propertyId,
                'date_start' => now()->addDay()->toDateString(),
                'date_end' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('date_start');

        $this->assertSame(0, \DB::table('gsc_import_runs')->count());
    }
}
