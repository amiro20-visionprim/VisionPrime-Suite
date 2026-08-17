<?php

declare(strict_types=1);

namespace App\Domains\Platform\Jobs;

use App\Domains\Platform\Models\Subscription;
use App\Domains\Platform\Services\TriageEngine;
use App\Domains\Workspace\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * حسگر روزانهٔ پلتفرم — وضعیت‌های واقعی را اسکن و به TriageEngine گزارش می‌کند:
 *   - اشتراک‌های نزدیک انقضا (۷ روز) → subscription.expiring
 *   - اشتراک‌های past_due → subscription.past_due
 *   - پرداخت‌های failed اخیر → payment.failed (تصمیم)
 *   - سایت‌های بدون heartbeat → site.disconnected
 *   - jobهای failed → job.failure
 *   - مصرف AI بالای ۸۰٪ سقف پلن → ai.cost_spike
 *   - صف تأیید (reviews/commands) → review.awaiting / command.awaiting (تصمیم)
 */
class CollectPlatformEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function handle(): void
    {
        $triage = app(TriageEngine::class);

        $this->triage = $triage;
        $this->scanSubscriptions();
        $this->scanFailedPayments();
        $this->scanDisconnectedSites();
        $this->scanFailedJobs();
        $this->scanAiUsage();
        $this->scanReviewQueue();
    }

    private TriageEngine $triage;

    private function scanSubscriptions(): void
    {
        // نزدیک انقضا (۷ روز آینده) و هنوز لغو نشده
        DB::table('subscriptions')
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->where('cancel_at_period_end', false)
            ->whereBetween('current_period_end', [now(), now()->addDays(7)])
            ->get()
            ->each(function ($row): void {
                $this->triage->record(
                    (int) $row->organization_id,
                    'subscription.expiring',
                    'warning',
                    ['subscription_id' => (int) $row->id, 'period_end' => (string) $row->current_period_end],
                );
            });

        // پرداخت معوق
        DB::table('subscriptions')
            ->where('status', Subscription::STATUS_PAST_DUE)
            ->get()
            ->each(function ($row): void {
                $this->triage->record(
                    (int) $row->organization_id,
                    'subscription.past_due',
                    'critical',
                    ['subscription_id' => (int) $row->id],
                );
            });
    }

    private function scanFailedPayments(): void
    {
        DB::table('payments')
            ->where('status', 'failed')
            ->where('paid_at', '>=', now()->subDays(3))
            ->get()
            ->each(function ($row): void {
                $this->triage->record(
                    (int) $row->organization_id,
                    'payment.failed',
                    'critical',
                    ['payment_id' => (int) $row->id, 'amount' => (int) $row->amount, 'reference' => (string) $row->reference],
                );
            });
    }

    private function scanDisconnectedSites(): void
    {
        $pairedSiteIds = DB::table('site_connections')
            ->whereIn('status', ['paired', 'connected'])
            ->distinct()
            ->pluck('site_id');

        Site::query()
            ->where('status', 'active')
            ->whereNotIn('id', $pairedSiteIds)
            ->get()
            ->each(function (Site $site): void {
                $this->triage->record(
                    (int) $site->organization_id,
                    'site.disconnected',
                    'warning',
                    ['site_id' => (int) $site->getKey(), 'site_name' => (string) $site->name],
                );
            });
    }

    private function scanFailedJobs(): void
    {
        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        if ($failed > 0) {
            $this->triage->record(
                null,
                'job.failure',
                'warning',
                ['count' => $failed],
            );
        }
    }

    private function scanAiUsage(): void
    {
        // مصرف ماهانهٔ هر سازمان از ai_generations.usage (output tokens)
        $usageByOrg = DB::table('ai_generations')
            ->where('created_at', '>=', now()->startOfMonth())
            ->get()
            ->groupBy('site_id')
            ->map(fn ($rows): int => $rows->sum(fn ($row): int => (int) (json_decode((string) $row->usage, true)['output_tokens'] ?? 0)));

        if ($usageByOrg->isEmpty()) {
            return;
        }

        $sites = DB::table('sites')->pluck('organization_id', 'id');
        $plans = DB::table('plans')->pluck('limits', 'id')->map(fn ($l): array => json_decode((string) $l, true) ?? []);

        DB::table('subscriptions')
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->get()
            ->each(function ($subscription) use ($usageByOrg, $sites, $plans): void {
                $orgId = (int) $subscription->organization_id;
                $siteIds = $sites->filter(fn ($org): bool => $org === $orgId)->keys();
                $tokens = $siteIds->sum(fn ($siteId): int => (int) ($usageByOrg->get((int) $siteId) ?? 0));

                $planLimits = $plans->get((int) $subscription->plan_id, []);
                $cap = (int) ($planLimits['max_ai_tokens_monthly'] ?? 0);
                if ($cap > 0 && $tokens >= (int) ($cap * 0.8)) {
                    $this->triage->record(
                        $orgId,
                        'ai.cost_spike',
                        'warning',
                        ['tokens_used' => $tokens, 'tokens_cap' => $cap],
                    );
                }
            });
    }

    private function scanReviewQueue(): void
    {
        $pendingReviews = DB::table('review_items')->whereNull('resolved_at')->count();
        if ($pendingReviews > 0) {
            $this->triage->record(
                null,
                'review.awaiting',
                'info',
                ['count' => $pendingReviews],
            );
        }

        $pendingCommands = DB::table('commands')
            ->where('status', 'pending_approval')
            ->count();
        if ($pendingCommands > 0) {
            $this->triage->record(
                null,
                'command.awaiting',
                'info',
                ['count' => $pendingCommands],
            );
        }
    }
}
