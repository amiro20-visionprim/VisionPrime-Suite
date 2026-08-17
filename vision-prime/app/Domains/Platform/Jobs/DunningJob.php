<?php

declare(strict_types=1);

namespace App\Domains\Platform\Jobs;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Platform\Models\Invoice;
use App\Domains\Platform\Models\Subscription;
use App\Domains\Platform\Services\InvoiceService;
use App\Domains\Platform\Services\SmsManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dunning — پیگیری پرداخت‌های معوق:
 *   روزانه اسکن فاکتورهای صادرشده با due_at گذشته → وضعیت overdue + اعلان به سازمان؛
 *   بعد از grace period (۵ روز از سررسید) → اشتراک suspend می‌شود.
 */
class DunningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $graceDays = 5;

    public function handle(): void
    {
        $invoiceService = app(InvoiceService::class);
        $audit = app(RecordAuditLog::class);
        $sms = app(SmsManager::class);

        $overdueCount = $invoiceService->overdueCheck();

        // F-02 (گسترش): پیامک یادآوری معوق به شمارهٔ سازمان — فقط یک‌بار per فاکتور
        $smsNotified = 0;
        $newOverdue = Invoice::query()
            ->where('status', Invoice::STATUS_OVERDUE)
            ->whereNull('sms_reminder_sent_at')
            ->with('organization')
            ->get();

        foreach ($newOverdue as $invoice) {
            $phone = $invoice->organization?->settings['phone'] ?? null;
            if (! is_string($phone) || $phone === '') {
                continue;
            }

            $sms->send(
                (string) $phone,
                sprintf(
                    'هشدار: فاکتور %s به مبلغ %s ریال برای «%s» معوق است. پس از %d روز اشتراک معلق می‌شود.',
                    (string) $invoice->number,
                    number_format((int) $invoice->total),
                    (string) ($invoice->organization?->name ?? 'سازمان'),
                    $this->graceDays,
                ),
            );

            $invoice->update(['sms_reminder_sent_at' => now(), 'updated_at' => now()]);
            $smsNotified++;
        }

        // تعلیق اشتراک‌هایی که بیش از grace period معوق‌اند
        $suspended = 0;
        $overdueInvoices = Invoice::query()
            ->where('status', Invoice::STATUS_OVERDUE)
            ->with('subscription')
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $subscription = $invoice->subscription;
            if ($subscription === null || ! $subscription->isActive()) {
                continue;
            }

            $graceEnd = $invoice->due_at->copy()->addDays($this->graceDays);
            if ($graceEnd->isPast()) {
                $subscription->update(['status' => Subscription::STATUS_SUSPENDED, 'auto_renew' => false, 'updated_at' => now()]);
                $suspended++;

                $audit->handle(
                    action: 'platform.dunning.subscription_suspended',
                    after: ['subscription_id' => $subscription->getKey(), 'invoice_id' => $invoice->getKey()],
                    organization: null,
                    source: 'schedule',
                );
            }
        }

        if ($overdueCount > 0 || $suspended > 0 || $smsNotified > 0) {
            $audit->handle(
                action: 'platform.dunning.executed',
                after: ['overdue' => $overdueCount, 'suspended' => $suspended, 'sms_notified' => $smsNotified],
                organization: null,
                source: 'schedule',
            );
        }
    }
}
