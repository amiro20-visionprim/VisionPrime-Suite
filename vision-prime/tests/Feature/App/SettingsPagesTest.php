<?php

declare(strict_types=1);

namespace Tests\Feature\App;

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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsPagesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private User $member;

    private int $seoManagerRoleId;

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
        $this->member = User::factory()->create(['name' => 'عضو', 'email' => 'member@test.ir']);
        $this->seoManagerRoleId = Role::query()->where('key', 'seo-manager')->valueOrFail('id');

        $this->createMembership($this->admin, 'agency-admin');
        $this->createMembership($this->member, 'client-viewer');
    }

    public function test_agency_admin_can_view_organization_settings_with_members_and_roles(): void
    {
        $this->actingAs($this->admin)
            ->get('/app/settings/organization')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Settings/Organization')
                ->has('members', 2)
                ->where('members.0.roleKey', 'agency-admin')
                ->has('roles', 9)
                ->where('roles.0.key', 'agency-admin')
                ->where('canManage', true));
    }

    public function test_admin_can_add_existing_user_as_member(): void
    {
        $invitee = User::factory()->create(['email' => 'invitee@test.ir']);

        $this->actingAs($this->admin)
            ->post('/app/settings/organization/members', [
                'email' => 'INVITEE@test.ir',
                'role_id' => $this->seoManagerRoleId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $this->organization->getKey(),
            'user_id' => $invitee->getKey(),
            'role_id' => $this->seoManagerRoleId,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->getKey(),
            'actor_id' => $this->admin->getKey(),
            'action' => 'organization.member_added',
        ]);
    }

    public function test_adding_unknown_email_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/app/settings/organization/members', [
                'email' => 'nobody@test.ir',
                'role_id' => $this->seoManagerRoleId,
            ])
            ->assertSessionHasErrors('email');

        $this->assertSame(2, Membership::query()->where('organization_id', $this->organization->getKey())->count());
    }

    public function test_admin_can_change_member_role(): void
    {
        $membership = Membership::query()->where('organization_id', $this->organization->getKey())
            ->where('user_id', $this->member->getKey())
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->put("/app/settings/organization/members/{$membership->getKey()}", [
                'role_id' => $this->seoManagerRoleId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('memberships', [
            'id' => $membership->getKey(),
            'role_id' => $this->seoManagerRoleId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->getKey(),
            'action' => 'organization.member_role_changed',
        ]);
    }

    public function test_admin_can_remove_member(): void
    {
        $membership = Membership::query()->where('organization_id', $this->organization->getKey())
            ->where('user_id', $this->member->getKey())
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->delete("/app/settings/organization/members/{$membership->getKey()}")
            ->assertRedirect();

        $this->assertDatabaseMissing('memberships', ['id' => $membership->getKey()]);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->getKey(),
            'action' => 'organization.member_removed',
        ]);
    }

    public function test_member_cannot_remove_themselves(): void
    {
        $selfMembership = Membership::query()->where('organization_id', $this->organization->getKey())
            ->where('user_id', $this->admin->getKey())
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->delete("/app/settings/organization/members/{$selfMembership->getKey()}")
            ->assertStatus(422);

        $this->assertDatabaseHas('memberships', ['id' => $selfMembership->getKey()]);
    }

    public function test_member_without_manage_permission_cannot_add_member(): void
    {
        $this->actingAs($this->member)
            ->post('/app/settings/organization/members', [
                'email' => 'invitee@test.ir',
                'role_id' => $this->seoManagerRoleId,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('memberships', [
            'organization_id' => $this->organization->getKey(),
            'user_id' => User::query()->where('email', 'invitee@test.ir')->value('id'),
        ]);
    }

    public function test_integrations_page_renders_connection_statuses(): void
    {
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
        $site = Site::query()->create([
            'organization_id' => $this->organization->getKey(),
            'project_id' => $project->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => 'سایت',
            'canonical_url' => 'https://site.example.ir',
            'status' => 'active',
        ]);

        \DB::table('gsc_accounts')->insertGetId([
            'organization_id' => $this->organization->getKey(),
            'google_subject' => 'sub-'.Str::random(8),
            'email' => 'gsc@test.ir',
            'token_ciphertext' => 'enc::test-token',
            'status' => 'connected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('site_connections')->insert([
            'site_id' => $site->getKey(),
            'status' => 'paired',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get('/app/settings/integrations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Settings/Integrations')
                ->where('gsc.connected', 1)
                ->where('gsc.propertiesCount', 0)
                ->where('wordpress.totalSites', 1)
                ->where('wordpress.pairedSites', 1)
                ->where('ai.isConfigured', false));
    }

    public function test_audit_log_page_lists_entries_and_filters(): void
    {
        \DB::table('audit_logs')->insert([
            'organization_id' => $this->organization->getKey(),
            'actor_id' => $this->admin->getKey(),
            'actor_type' => 'user',
            'action' => 'site.created',
            'source' => 'web',
            'occurred_at' => now(),
        ]);
        \DB::table('audit_logs')->insert([
            'organization_id' => $this->organization->getKey(),
            'actor_id' => $this->admin->getKey(),
            'actor_type' => 'user',
            'action' => 'client.created',
            'source' => 'web',
            'occurred_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get('/app/settings/audit-log')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Settings/AuditLog')
                ->has('logs.data', 2)
                ->has('actionOptions', 2));

        $this->actingAs($this->admin)
            ->get('/app/settings/audit-log?action=site.created')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('App/Settings/AuditLog')
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'site.created'));
    }

    public function test_member_without_audit_permission_cannot_view_audit_log(): void
    {
        $this->actingAs($this->member)
            ->get('/app/settings/audit-log')
            ->assertForbidden();
    }

    public function test_member_without_view_permission_cannot_view_organization_members(): void
    {
        $this->actingAs($this->member)
            ->get('/app/settings/organization')
            ->assertForbidden();
    }

    public function test_member_without_connector_or_gsc_permission_cannot_view_integrations(): void
    {
        $this->actingAs($this->member)
            ->get('/app/settings/integrations')
            ->assertForbidden();
    }

    private function createMembership(User $user, string $roleKey): Membership
    {
        return Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->where('key', $roleKey)->valueOrFail('id'),
            'status' => 'active',
        ]);
    }
}
