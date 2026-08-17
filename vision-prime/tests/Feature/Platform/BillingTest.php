<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domains\Organization\Models\Organization;
use App\Domains\Platform\Jobs\DunningJob;
use App\Domains\Platform\Models\Invoice;
use App\Domains\Platform\Models\Payment;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\Subscription;
use App\Domains\Platform\Services\InvoiceService;
use App\Domains\Platform\Services\PaymentService;
use App\Domains\Platform\Services\SubscriptionService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'public_id' => '01JZXT00000000000000000001',
            'name' => 'آژانس تست',
            'slug' => 'test-agency',
            'status' => 'active',
        ]);

        $this->plan = Plan::create([
            'key' => 'growth-test',
            'name' => 'رشد',
            'description' => 'پلن تست',
            'price_monthly' => 3_500_000,
            'price_yearly' => 35_000_000,
            'currency' => 'IRT',
            'limits' => ['max_sites' => 5, 'max_ai_tokens_monthly' => 2_000_000],
            'features' => ['trial_days' => 14, 'auto_publish' => true],
            'is_active' => true,
            'sort' => 1,
        ]);

        $this->actingAs(User::factory()->create());
    }

    public function test_activate_creates_trialing_subscription(): void
    {
        $subscription = app(SubscriptionService::class)->activate($this->org, $this->plan, trialDays: 14);

        $this->assertSame(Subscription::STATUS_TRIALING, $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->isActive());
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.subscription.activated']);
    }

    public function test_activate_without_trial_is_active(): void
    {
        $subscription = app(SubscriptionService::class)->activate($this->org, $this->plan, trialDays: 0);

        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_renew_extends_period_and_clears_trial(): void
    {
        $subscription = app(SubscriptionService::class)->activate($this->org, $this->plan, trialDays: 14);
        // دورهٔ قبلی را به گذشته می‌بریم تا «تمدید به آینده» قطعاً قابل سنجش باشد
        $subscription->update(['current_period_end' => now()->subDay()]);

        $renewed = app(SubscriptionService::class)->renew($subscription);

        $this->assertSame(Subscription::STATUS_ACTIVE, $renewed->status);
        $this->assertNull($renewed->trial_ends_at);
        $this->assertTrue($renewed->current_period_end->isFuture());
        $this->assertTrue($renewed->current_period_end->gt(now()->addDays(25)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.subscription.renewed']);
    }

    public function test_cancel_at_period_end_keeps_active(): void
    {
        $subscription = app(SubscriptionService::class)->activate($this->org, $this->plan, trialDays: 0);
        $canceled = app(SubscriptionService::class)->cancel($subscription, atPeriodEnd: true);

        $this->assertTrue($canceled->cancel_at_period_end);
        $this->assertFalse($canceled->auto_renew);
        $this->assertSame(Subscription::STATUS_ACTIVE, $canceled->status);
    }

    public function test_suspend_and_reactivate(): void
    {
        $subscription = app(SubscriptionService::class)->activate($this->org, $this->plan, trialDays: 0);
        $suspended = app(SubscriptionService::class)->suspend($subscription);

        $this->assertSame(Subscription::STATUS_SUSPENDED, $suspended->status);
        $this->assertFalse($suspended->isActive());

        $reactivated = app(SubscriptionService::class)->reactivate($suspended);
        $this->assertSame(Subscription::STATUS_ACTIVE, $reactivated->status);
        $this->assertTrue($reactivated->auto_renew);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.subscription.reactivated']);
    }

    public function test_manual_payment_marks_paid_and_audits(): void
    {
        $subscription = app(SubscriptionService::class)->activate($this->org, $this->plan, trialDays: 0);

        $payment = app(PaymentService::class)->recordManual($this->org, 3_500_000, $subscription, method: 'bank');

        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('bank', $payment->method);
        $this->assertNotNull($payment->paid_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.payment.recorded']);
    }

    public function test_payment_mark_failed_and_refund(): void
    {
        $subscription = app(SubscriptionService::class)->activate($this->org, $this->plan, trialDays: 0);
        $payment = app(PaymentService::class)->recordManual($this->org, 3_500_000, $subscription);

        $failed = app(PaymentService::class)->markFailed($payment);
        $this->assertSame(Payment::STATUS_FAILED, $failed->status);

        $refunded = app(PaymentService::class)->refund($failed);
        $this->assertSame(Payment::STATUS_REFUNDED, $refunded->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.payment.refunded']);
    }

    public function test_invoice_generation_has_tax_and_unique_number(): void
    {
        $subscription = app(SubscriptionService::class)->activate($this->org, $this->plan, trialDays: 0);
        $invoice = app(InvoiceService::class)->generateForSubscription($subscription);

        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame(3_500_000, $invoice->amount);
        $this->assertSame(315_000, $invoice->tax);
        $this->assertSame(3_815_000, $invoice->total);
        $this->assertStringStartsWith('INV-', $invoice->number);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.invoice.issued']);
    }

    public function test_overdue_check_marks_past_due_invoices(): void
    {
        $subscription = app(SubscriptionService::class)->activate($this->org, $this->plan, trialDays: 0);
        app(InvoiceService::class)->generateForSubscription($subscription);

        Invoice::query()->update(['due_at' => now()->subDay()]);

        $count = app(InvoiceService::class)->overdueCheck();

        $this->assertSame(1, $count);
        $this->assertSame(Invoice::STATUS_OVERDUE, Invoice::first()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.invoice.overdue_batch']);
    }

    public function test_dunning_suspends_after_grace_period(): void
    {
        $subscription = app(SubscriptionService::class)->activate($this->org, $this->plan, trialDays: 0);
        app(InvoiceService::class)->generateForSubscription($subscription);

        // فاکتور را به ۶ روز پیش برسان (بیش از grace period = ۵ روز)
        Invoice::query()->update(['due_at' => now()->subDays(6)]);

        app(DunningJob::class)->handle();

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('invoices', ['status' => 'overdue']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'platform.dunning.subscription_suspended']);
    }

    public function test_backup_command_creates_sqlite_file(): void
    {
        $this->artisan('platform:backup-db')
            ->expectsOutputToContain('بکاپ ساخته شد')
            ->assertExitCode(0);

        $backups = collect(File::files(storage_path('backups')))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.sqlite'));

        $this->assertGreaterThanOrEqual(1, $backups->count());
    }

    public function test_plan_limits_and_remaining_days(): void
    {
        $this->assertSame(5, $this->plan->limits()['max_sites']);
        $this->assertTrue($this->plan->isActive());
        $this->assertTrue($this->plan->features()['auto_publish']);

        $subscription = app(SubscriptionService::class)->activate($this->org, $this->plan, trialDays: 0);
        $this->assertGreaterThanOrEqual(27, $subscription->remainingDays());
    }
}
