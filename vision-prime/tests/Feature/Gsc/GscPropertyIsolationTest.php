<?php

declare(strict_types=1);

namespace Tests\Feature\Gsc;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cross-tenant guard برای انتخاب حساب سرچ کنسول:
 * یک عضو سازمان A نباید بتواند gsc_account سازمان B را به سایت خودش ببندد.
 */
class GscPropertyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private Site $site;

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
    }

    private function createAccount(Organization $org, string $subject): int
    {
        return \DB::table('gsc_accounts')->insertGetId([
            'organization_id' => $org->getKey(),
            'google_subject' => $subject,
            'email' => 'gsc-'.Str::lower(Str::random(6)).'@test.ir',
            'token_ciphertext' => 'encrypted',
            'status' => 'connected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_admin_can_select_own_account_property(): void
    {
        $accountId = $this->createAccount($this->organization, 'sub-own');

        $this->actingAs($this->admin)
            ->post('/app/gsc/properties', [
                'site_id' => $this->site->getKey(),
                'gsc_account_id' => $accountId,
                'property_uri' => 'https://example.ir',
                'property_type' => 'url-prefix',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('gsc_properties', [
            'site_id' => $this->site->getKey(),
            'gsc_account_id' => $accountId,
            'property_uri' => 'https://example.ir',
        ]);
    }

    public function test_another_organizations_gsc_account_is_rejected(): void
    {
        $other = Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'سازمان دیگر',
            'slug' => 'org-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
        $foreignAccountId = $this->createAccount($other, 'sub-foreign');

        $this->actingAs($this->admin)
            ->post('/app/gsc/properties', [
                'site_id' => $this->site->getKey(),
                'gsc_account_id' => $foreignAccountId,
                'property_uri' => 'https://foreign.ir',
                'property_type' => 'url-prefix',
            ])
            ->assertSessionHasErrors('gsc_account_id');

        $this->assertSame(0, \DB::table('gsc_properties')->count());
    }

    public function test_site_of_another_organization_is_still_rejected(): void
    {
        $other = Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'سازمان دیگر ۲',
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
        $ownAccountId = $this->createAccount($this->organization, 'sub-own-2');

        $this->actingAs($this->admin)
            ->post('/app/gsc/properties', [
                'site_id' => $otherSite->getKey(),
                'gsc_account_id' => $ownAccountId,
                'property_uri' => 'https://other.ir',
                'property_type' => 'url-prefix',
            ])
            ->assertNotFound();

        $this->assertSame(0, \DB::table('gsc_properties')->count());
    }
}
