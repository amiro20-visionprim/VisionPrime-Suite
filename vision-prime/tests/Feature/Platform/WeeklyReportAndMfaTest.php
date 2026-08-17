<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Organization;
use App\Domains\Platform\Jobs\SendWeeklyReport;
use App\Domains\Platform\Models\Payment;
use App\Domains\Platform\Services\PlatformSettingsService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WeeklyReportAndMfaTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['key' => 'super-admin', 'name' => 'Super Admin']);
        $this->org = Organization::create([
            'public_id' => '01JZXT0000000000000000000F',
            'name' => 'Test Agency',
            'slug' => 'test-agency',
            'status' => 'active',
        ]);

        $this->superAdmin = User::factory()->create(['name' => 'Boss', 'email' => 'boss@test.local']);
        DB::table('memberships')->insert([
            'organization_id' => $this->org->getKey(),
            'user_id' => $this->superAdmin->getKey(),
            'role_id' => $role->getKey(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_weekly_report_sends_email_notification_and_sms_to_owner(): void
    {
        config(['services.sms.owner_phone' => '09123456789']);

        Payment::create([
            'organization_id' => $this->org->getKey(),
            'amount' => 2500000,
            'currency' => 'IRT',
            'method' => 'manual',
            'status' => Payment::STATUS_PAID,
            'reference' => 'ref-week-1',
            'paid_at' => now(),
        ]);

        DB::table('platform_events')->insert([
            'organization_id' => null,
            'type' => 'payment.failed',
            'severity' => 'critical',
            'triage' => 'decision',
            'payload' => json_encode(['amount' => 1200000]),
            'resolved_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new SendWeeklyReport)->handle();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->superAdmin->getKey(),
            'type' => 'App\Notifications\WeeklyReportNotification',
        ]);

        $this->assertDatabaseHas('sms_logs', [
            'to' => '09123456789',
            'status' => 'sent',
        ]);
    }

    public function test_weekly_report_without_owner_phone_skips_sms_silently(): void
    {
        config(['services.sms.owner_phone' => '']);

        (new SendWeeklyReport)->handle();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->superAdmin->getKey(),
            'type' => 'App\Notifications\WeeklyReportNotification',
        ]);

        $this->assertDatabaseCount('sms_logs', 0);
    }

    public function test_mfa_is_optional_by_default_superadmin_without_mfa_can_access(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/platform/dashboard')
            ->assertOk();
    }

    public function test_mfa_required_policy_redirects_superadmin_without_mfa_to_settings(): void
    {
        app(PlatformSettingsService::class)->set('mfa_required', true);

        $this->actingAs($this->superAdmin)
            ->get('/platform/dashboard')
            ->assertRedirect(route('platform.mfa.settings'));
    }

    public function test_user_with_mfa_enabled_is_challenged_until_verified(): void
    {
        $this->superAdmin->forceFill([
            'mfa_secret' => 'ABCDEFGHIJKLMNOP',
            'mfa_enabled' => true,
        ])->save();

        $this->actingAs($this->superAdmin)
            ->get('/platform/dashboard')
            ->assertRedirect(route('platform.mfa.challenge'));
    }

    public function test_toggle_requirement_updates_platform_setting(): void
    {
        $this->actingAs($this->superAdmin)
            ->post('/platform/mfa/require', ['required' => true])
            ->assertRedirect();

        $this->assertTrue(app(PlatformSettingsService::class)->bool('mfa_required', false));
    }
}
