<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Membership;
use App\Domains\Organization\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_user_without_an_organization_is_redirected_to_onboarding(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/app/dashboard')->assertRedirect(route('app.onboarding'));
    }

    public function test_user_can_create_an_organization_from_onboarding(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/app/onboarding', ['name' => 'آژانس رشد آبی']);

        $organization = Organization::query()->where('name', 'آژانس رشد آبی')->firstOrFail();
        $membership = Membership::query()->where('organization_id', $organization->getKey())->where('user_id', $user->getKey())->firstOrFail();

        $response->assertRedirect(route('app.dashboard'));
        $this->assertSame('agency-admin', $membership->role->key);
        $this->assertSame($organization->getKey(), session('current_organization_id'));
    }

    public function test_user_cannot_switch_to_an_organization_without_membership(): void
    {
        $user = User::factory()->create();
        $memberOrganization = Organization::query()->create([
            'public_id' => '01J00000000000000000000001',
            'name' => 'سازمان کاربر',
            'slug' => 'member-organization',
            'status' => 'active',
        ]);
        $otherOrganization = Organization::query()->create([
            'public_id' => '01J00000000000000000000000',
            'name' => 'سازمان دیگر',
            'slug' => 'other-organization',
            'status' => 'active',
        ]);
        Membership::query()->create([
            'organization_id' => $memberOrganization->getKey(),
            'user_id' => $user->getKey(),
            'role_id' => Role::query()->where('key', 'agency-admin')->valueOrFail('id'),
            'status' => 'active',
        ]);

        $this->actingAs($user)->put("/app/current-organization/{$otherOrganization->getKey()}")->assertForbidden();
    }

    public function test_role_and_permission_seed_is_available(): void
    {
        $this->assertTrue(Role::query()->where('key', 'agency-admin')->exists());
        $this->assertDatabaseHas('permissions', ['key' => 'automation_policy.manage.assigned']);
    }
}
