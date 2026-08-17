<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Organization;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Services\SubscriptionService;
use App\Domains\Platform\Services\TriageEngine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Role $superAdminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::create(['key' => 'super-admin', 'name' => 'Super Admin', 'is_system' => true]);
        Role::create(['key' => 'agency-admin', 'name' => 'Agency Admin', 'is_system' => true]);

        $this->org = Organization::create([
            'public_id' => '01JZXT00000000000000000002',
            'name' => 'آژانس پلتفرم',
            'slug' => 'platform-agency',
            'status' => 'active',
        ]);
    }

    private function userWithRole(string $roleKey): User
    {
        $user = User::factory()->create();
        \DB::table('memberships')->insert([
            'organization_id' => $this->org->id,
            'user_id' => $user->id,
            'role_id' => Role::where('key', $roleKey)->firstOrFail()->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_non_super_admin_gets_403(): void
    {
        $user = $this->userWithRole('agency-admin');
        $this->actingAs($user);

        $this->get('/platform/dashboard')->assertForbidden();
    }

    public function test_super_admin_can_open_dashboard(): void
    {
        $user = $this->userWithRole('super-admin');
        $this->actingAs($user);

        $this->get('/platform/dashboard')->assertOk();
    }

    public function test_super_admin_dashboard_shows_kpis_and_events(): void
    {
        $user = $this->userWithRole('super-admin');
        $this->actingAs($user);

        Plan::create([
            'key' => 'growth-p',
            'name' => 'رشد',
            'price_monthly' => 3_500_000,
            'price_yearly' => 35_000_000,
            'currency' => 'IRT',
            'limits' => ['max_sites' => 5],
            'features' => [],
            'is_active' => true,
            'sort' => 1,
        ]);
        app(SubscriptionService::class)->activate($this->org, Plan::first(), trialDays: 0);

        $response = $this->get('/platform/dashboard')->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/Dashboard')
            ->has('kpis.orgs_active')
            ->has('trend')
            ->has('recentEvents'));
    }

    public function test_organizations_index_and_show(): void
    {
        $user = $this->userWithRole('super-admin');
        $this->actingAs($user);

        $this->get('/platform/organizations')->assertOk();
        $this->get('/platform/organizations/'.$this->org->id)->assertOk();
    }

    public function test_operations_page_and_emergency_stop(): void
    {
        $user = $this->userWithRole('super-admin');
        $this->actingAs($user);

        $this->get('/platform/operations')->assertOk();

        $this->post('/platform/emergency-stop', ['reason' => 'تست توقف اضطراری'])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.emergency_stop_activated']);
    }

    public function test_suspend_and_activate_organization(): void
    {
        $user = $this->userWithRole('super-admin');
        $this->actingAs($user);

        $this->post("/platform/organizations/{$this->org->id}/suspend", ['reason' => 'عدم پرداخت'])
            ->assertRedirect();
        $this->assertDatabaseHas('organizations', ['id' => $this->org->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.organization.suspended']);

        $this->post("/platform/organizations/{$this->org->id}/activate")
            ->assertRedirect();
        $this->assertDatabaseHas('organizations', ['id' => $this->org->id, 'status' => 'active']);
    }

    public function test_impersonation_start_and_stop_with_audit(): void
    {
        $super = $this->userWithRole('super-admin');
        $target = $this->userWithRole('agency-admin');

        $this->actingAs($super);
        $this->post("/platform/organizations/{$this->org->id}/impersonate/{$target->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.impersonation.started']);
        $this->assertEquals($target->id, auth()->id());

        // اکشن حساس هنگام impersonation مسدود است
        $this->post("/platform/organizations/{$this->org->id}/suspend", ['reason' => 'x'])
            ->assertForbidden();

        $this->post('/platform/impersonation/stop')->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.impersonation.stopped']);
        $this->assertEquals($super->id, auth()->id());
    }

    public function test_triage_classification_and_resolve(): void
    {
        $triage = app(TriageEngine::class);

        // decision type
        $id = $triage->record($this->org->id, 'payment.failed', 'critical', ['amount' => 1000]);
        $this->assertDatabaseHas('platform_events', ['id' => $id, 'triage' => 'decision', 'type' => 'payment.failed']);

        // exception type
        $id2 = $triage->record($this->org->id, 'subscription.expiring', 'warning', ['subscription_id' => 1]);
        $this->assertDatabaseHas('platform_events', ['id' => $id2, 'triage' => 'exception']);

        // normal type
        $id3 = $triage->record(null, 'review.awaiting', 'info', ['count' => 2]);
        $this->assertDatabaseHas('platform_events', ['id' => $id3, 'triage' => 'decision']);

        $decisions = $triage->pendingDecisions();
        $this->assertNotEmpty($decisions);

        $triage->resolve((int) $decisions[0]['id'], $this->userWithRole('super-admin')->id, 'یادآوری ارسال شد');
        $this->assertDatabaseHas('platform_events', ['id' => $decisions[0]['id'], 'resolved_at' => now()->toDateTimeString()]);
    }
}
