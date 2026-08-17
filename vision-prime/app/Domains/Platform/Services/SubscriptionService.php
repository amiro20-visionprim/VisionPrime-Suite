<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Models\Organization;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * مدیریت چرخهٔ حیات اشتراک سازمان — تمام انتقال‌های وضعیت این‌جا انجام می‌شود
 * (نه در کنترلر) و هر اکشن در audit_logs ثبت می‌شود.
 */
class SubscriptionService
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    /**
     * فعال‌سازی اشتراک برای سازمان (اولین بار یا جایگزینی پلن قبلی).
     * اگر اشتراک فعال موجود باشد، cancel می‌شود و اشتراک جدید ساخته می‌شود.
     */
    public function activate(Organization $org, Plan $plan, ?int $trialDays = null): Subscription
    {
        return DB::transaction(function () use ($org, $plan, $trialDays): Subscription {
            Subscription::query()
                ->where('organization_id', $org->getKey())
                ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
                ->update(['cancel_at_period_end' => true, 'updated_at' => now()]);

            $trialDays ??= $plan->features()['trial_days'] ?? 0;

            $subscription = Subscription::query()->create([
                'organization_id' => $org->getKey(),
                'plan_id' => $plan->getKey(),
                'status' => $trialDays > 0 ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE,
                'trial_ends_at' => $trialDays > 0 ? now()->addDays($trialDays) : null,
                'starts_at' => now(),
                'current_period_end' => now()->addMonth(),
                'auto_renew' => true,
                'cancel_at_period_end' => false,
            ]);

            $this->audit->handle(
                action: 'platform.subscription.activated',
                subject: $subscription,
                after: [
                    'organization_id' => $org->getKey(),
                    'plan_id' => $plan->getKey(),
                    'status' => $subscription->status,
                    'trial_days' => $trialDays,
                ],
            );

            return $subscription;
        });
    }

    public function renew(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'current_period_end' => now()->addMonth(),
            'cancel_at_period_end' => false,
            'trial_ends_at' => null,
        ]);

        $this->audit->handle(
            action: 'platform.subscription.renewed',
            subject: $subscription,
            after: ['status' => $subscription->status, 'current_period_end' => $subscription->current_period_end?->toIso8601String()],
        );

        return $subscription;
    }

    public function cancel(Subscription $subscription, bool $atPeriodEnd = true): Subscription
    {
        $subscription->update([
            'cancel_at_period_end' => $atPeriodEnd,
            'auto_renew' => false,
        ]);

        if (! $atPeriodEnd) {
            $subscription->update(['status' => Subscription::STATUS_CANCELED]);
        }

        $this->audit->handle(
            action: 'platform.subscription.canceled',
            subject: $subscription,
            after: ['cancel_at_period_end' => $atPeriodEnd, 'status' => $subscription->status],
        );

        return $subscription;
    }

    public function suspend(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => Subscription::STATUS_SUSPENDED, 'auto_renew' => false]);

        $this->audit->handle(
            action: 'platform.subscription.suspended',
            subject: $subscription,
            after: ['status' => $subscription->status],
        );

        return $subscription;
    }

    public function reactivate(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'auto_renew' => true,
            'cancel_at_period_end' => false,
        ]);

        $this->audit->handle(
            action: 'platform.subscription.reactivated',
            subject: $subscription,
            after: ['status' => $subscription->status],
        );

        return $subscription;
    }
}
