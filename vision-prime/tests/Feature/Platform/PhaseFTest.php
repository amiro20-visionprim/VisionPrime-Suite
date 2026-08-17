<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domains\Automation\Jobs\RemindScheduledPublishes;
use App\Domains\Identity\Models\Role;
use App\Domains\Organization\Models\Organization;
use App\Domains\Platform\Jobs\DunningJob;
use App\Domains\Platform\Models\Invoice;
use App\Domains\Platform\Models\Payment;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Services\AiTriageSummary;
use App\Domains\Platform\Services\InvoiceService;
use App\Domains\Platform\Services\PaymentGatewayManager;
use App\Domains\Platform\Services\SmsManager;
use App\Domains\Platform\Services\SubscriptionService;
use App\Domains\Platform\Services\Totp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseFTest extends TestCase
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

    public function test_gateway_manager_lists_all_drivers(): void
    {
        $manager = app(PaymentGatewayManager::class);
        $keys = array_column($manager->options(), 'key');

        $this->assertContains('zarinpal', $keys);
        $this->assertContains('aqayepardakht', $keys);
        $this->assertContains('manual', $keys);
    }

    public function test_zarinpal_sandbox_initiate_and_verify(): void
    {
        config(['services.zarinpal.merchant_id' => '']);

        $payment = Payment::create([
            'organization_id' => $this->org->getKey(),
            'amount' => 120000,
            'currency' => 'IRT',
            'method' => 'zarinpal',
            'status' => Payment::STATUS_PENDING,
            'reference' => 'ref-zarinpal-1',
        ]);

        $manager = app(PaymentGatewayManager::class);
        $driver = $manager->get('zarinpal');
        $result = $driver->initiate($payment, route('platform.payments.callback', ['gateway' => 'zarinpal', 'transaction' => 'x'], absolute: false));

        $this->assertArrayHasKey('redirect_url', $result);
        $this->assertArrayHasKey('transaction_id', $result);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);

        $ok = $driver->verify($payment->fresh(), ['Status' => 'OK']);
        $this->assertTrue($ok);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
    }

    public function test_aqayepardakht_sandbox_initiate_and_verify(): void
    {
        config(['services.aqayepardakht.pin' => '']);

        $payment = Payment::create([
            'organization_id' => $this->org->getKey(),
            'amount' => 85000,
            'currency' => 'IRT',
            'method' => 'aqayepardakht',
            'status' => Payment::STATUS_PENDING,
            'reference' => 'ref-aqp-1',
        ]);

        $manager = app(PaymentGatewayManager::class);
        $driver = $manager->get('aqayepardakht');
        $result = $driver->initiate($payment, route('platform.payments.callback', ['gateway' => 'aqayepardakht', 'transaction' => 'x'], absolute: false));

        $this->assertArrayHasKey('redirect_url', $result);
        $this->assertArrayHasKey('transaction_id', $result);

        $ok = $driver->verify($payment->fresh(), ['status' => '1']);
        $this->assertTrue($ok);
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
    }

    public function test_sms_manager_sends_and_logs(): void
    {
        $manager = app(SmsManager::class);
        $result = $manager->send('09120000000', 'پیام تست');

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('sms_logs', ['to' => '09120000000', 'status' => 'sent']);
    }

    public function test_totp_generates_and_verifies(): void
    {
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $code = $totp->code($secret);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue($totp->verify($secret, $code));
        $this->assertFalse($totp->verify($secret, '000000'));

        $backup = $totp->backupCodes(10);
        $this->assertCount(10, $backup);
    }

    public function test_ai_triage_summary_falls_back_offline(): void
    {
        config(['services.platform_ai.api_key' => '']);

        $summary = app(AiTriageSummary::class)->summarize([
            ['type' => 'payment.failed', 'severity' => 'critical', 'organization_id' => 1, 'created_at' => now()->toDateTimeString()],
            ['type' => 'subscription.expiring', 'severity' => 'warning', 'organization_id' => 1, 'created_at' => now()->toDateTimeString()],
        ]);

        $this->assertSame('rule', $summary['source']);
        $this->assertStringContainsString('بحرانی', $summary['summary']);
        $this->assertCount(2, $summary['priority']);
        $this->assertSame('payment.failed', $summary['priority'][0]['type']);
    }

    public function test_mfa_enable_flow_via_http(): void
    {
        // شروع setup
        $this->actingAs($this->superAdmin)
            ->post('/platform/mfa/setup')
            ->assertRedirect();

        $secret = $this->superAdmin->fresh()->mfa_secret;
        $this->assertNotNull($secret);

        $code = app(Totp::class)->code((string) $secret);

        $this->actingAs($this->superAdmin)
            ->post('/platform/mfa/enable', ['code' => $code])
            ->assertRedirect();

        $user = $this->superAdmin->fresh();
        $this->assertTrue((bool) $user->mfa_enabled);
        $this->assertCount(10, $user->mfa_backup_codes);
    }

    public function test_mfa_challenge_blocks_platform_until_verified(): void
    {
        $totp = app(Totp::class);
        $secret = $totp->generateSecret();
        $this->superAdmin->forceFill([
            'mfa_secret' => $secret,
            'mfa_enabled' => true,
            'mfa_enabled_at' => now(),
        ])->save();

        // بدون تأیید MFA → هدایت به چالش
        $this->actingAs($this->superAdmin)
            ->get('/platform/dashboard')
            ->assertRedirect(route('platform.mfa.challenge'));

        // تأیید با کد صحیح → دسترسی
        $this->actingAs($this->superAdmin)
            ->post('/platform/mfa/verify', ['code' => $totp->code($secret)])
            ->assertRedirect(route('platform.dashboard'));

        $this->actingAs($this->superAdmin)
            ->get('/platform/dashboard')
            ->assertOk();
    }

    public function test_sms_panel_page_accessible(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/platform/sms')
            ->assertOk();
    }

    public function test_dunning_sends_sms_to_org_phone_for_overdue(): void
    {
        $this->org->update(['settings' => ['phone' => '09121112233']]);

        $plan = Plan::create([
            'key' => 'sms-test-plan',
            'name' => 'پلن تست SMS',
            'price_monthly' => 500_000,
            'price_yearly' => 5_000_000,
            'currency' => 'IRT',
            'is_active' => true,
            'sort' => 1,
        ]);
        $subscription = app(SubscriptionService::class)->activate($this->org, $plan, trialDays: 0);
        app(InvoiceService::class)->generateForSubscription($subscription);
        Invoice::query()->update(['due_at' => now()->subDay()]);

        app(DunningJob::class)->handle();

        $this->assertDatabaseHas('invoices', ['status' => 'overdue']);
        $this->assertNotNull(Invoice::first()?->sms_reminder_sent_at);
        $this->assertDatabaseHas('sms_logs', ['to' => '09121112233', 'status' => 'sent']);
    }

    public function test_scheduled_publish_reminder_sends_sms_to_org_phone(): void
    {
        $this->org->update(['settings' => ['phone' => '09125556677']]);

        $clientId = DB::table('clients')->insertGetId([
            'organization_id' => $this->org->getKey(),
            'public_id' => '01JZXT0000000000000000000C',
            'name' => 'Client SMS',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $projectId = DB::table('projects')->insertGetId([
            'organization_id' => $this->org->getKey(),
            'client_id' => $clientId,
            'public_id' => '01JZXT0000000000000000000P',
            'name' => 'Project SMS',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siteId = DB::table('sites')->insertGetId([
            'organization_id' => $this->org->getKey(),
            'project_id' => $projectId,
            'public_id' => '01JZXT0000000000000000000S',
            'name' => 'Site SMS',
            'canonical_url' => 'https://sms.example.test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commands')->insert([
            'site_id' => $siteId,
            'source_type' => 'ai_draft',
            'type' => 'publish_new_article',
            'risk_tier' => 'low',
            'payload' => json_encode(['title' => 'مقالهٔ فردا']),
            'idempotency_key' => 'sms-reminder-'.uniqid(),
            'status' => 'scheduled',
            'scheduled_for' => now()->addHours(12),
            'expires_at' => now()->addDays(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(RemindScheduledPublishes::class)->handle();

        $this->assertDatabaseHas('sms_logs', ['to' => '09125556677', 'status' => 'sent']);
        $this->assertDatabaseMissing('commands', ['reminder_sent_at' => null]);
    }
}
