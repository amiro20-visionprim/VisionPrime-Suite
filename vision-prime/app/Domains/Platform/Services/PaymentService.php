<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Models\Organization;
use App\Domains\Platform\Models\Invoice;
use App\Domains\Platform\Models\Payment;
use App\Domains\Platform\Models\Subscription;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function recordManual(
        Organization $org,
        int $amount,
        ?Subscription $subscription = null,
        ?string $reference = null,
        string $method = 'manual',
        ?Invoice $invoice = null,
    ): Payment {
        $payment = Payment::query()->create([
            'organization_id' => $org->getKey(),
            'subscription_id' => $subscription?->getKey(),
            'amount' => $amount,
            'currency' => 'IRT',
            'method' => $method,
            'status' => Payment::STATUS_PAID,
            'reference' => $reference ?? (string) Str::ulid(),
            'paid_at' => now(),
        ]);

        if ($invoice !== null) {
            $invoice->update([
                'payment_id' => $payment->getKey(),
                'status' => Invoice::STATUS_PAID,
            ]);
        }

        $this->audit->handle(
            action: 'platform.payment.recorded',
            subject: $payment,
            after: [
                'organization_id' => $org->getKey(),
                'amount' => $amount,
                'method' => $method,
                'status' => $payment->status,
            ],
        );

        return $payment;
    }

    public function markPaid(Payment $payment): Payment
    {
        $payment->update(['status' => Payment::STATUS_PAID, 'paid_at' => now()]);

        $this->audit->handle(
            action: 'platform.payment.marked_paid',
            subject: $payment,
            after: ['status' => $payment->status],
        );

        return $payment;
    }

    public function markFailed(Payment $payment): Payment
    {
        $payment->update(['status' => Payment::STATUS_FAILED]);

        $this->audit->handle(
            action: 'platform.payment.marked_failed',
            subject: $payment,
            after: ['status' => $payment->status],
        );

        return $payment;
    }

    public function refund(Payment $payment): Payment
    {
        $payment->update(['status' => Payment::STATUS_REFUNDED]);

        $this->audit->handle(
            action: 'platform.payment.refunded',
            subject: $payment,
            after: ['status' => $payment->status],
        );

        return $payment;
    }
}
