<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Models\Organization;
use App\Domains\Platform\Models\Invoice;
use App\Domains\Platform\Models\Payment;
use App\Domains\Platform\Models\Subscription;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * صدور فاکتور برای دورهٔ اشتراک (مبلغ پلن + مالیات ۹٪).
     */
    public function generateForSubscription(Subscription $subscription, ?int $overridesAmount = null): Invoice
    {
        $amount = $overridesAmount ?? $subscription->plan->price_monthly;
        $tax = (int) round($amount * 0.09);
        $total = $amount + $tax;

        $invoice = Invoice::query()->create([
            'organization_id' => $subscription->organization_id,
            'subscription_id' => $subscription->getKey(),
            'number' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'amount' => $amount,
            'tax' => $tax,
            'total' => $total,
            'status' => Invoice::STATUS_ISSUED,
            'issued_at' => now(),
            'due_at' => now()->addDays(7),
        ]);

        $this->audit->handle(
            action: 'platform.invoice.issued',
            subject: $invoice,
            after: [
                'organization_id' => $subscription->organization_id,
                'number' => $invoice->number,
                'total' => $total,
            ],
        );

        return $invoice;
    }

    public function markPaid(Invoice $invoice, ?Payment $payment = null): Invoice
    {
        $invoice->update([
            'status' => Invoice::STATUS_PAID,
            'payment_id' => $payment?->getKey() ?? $invoice->payment_id,
        ]);

        $this->audit->handle(
            action: 'platform.invoice.marked_paid',
            subject: $invoice,
            after: ['status' => $invoice->status],
        );

        return $invoice;
    }

    /**
     * اسکن فاکتورهای صادرشده با due_at گذشته → معوق.
     *
     * @return int تعداد فاکتورهای به‌روزشده
     */
    public function overdueCheck(?Organization $scope = null): int
    {
        $query = Invoice::query()
            ->where('status', Invoice::STATUS_ISSUED)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());

        if ($scope !== null) {
            $query->where('organization_id', $scope->getKey());
        }

        $count = $query->update(['status' => Invoice::STATUS_OVERDUE, 'updated_at' => now()]);

        if ($count > 0) {
            $this->audit->handle(
                action: 'platform.invoice.overdue_batch',
                after: ['count' => $count],
            );
        }

        return (int) $count;
    }
}
