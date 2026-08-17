<?php

declare(strict_types=1);

namespace App\Domains\Platform\Jobs;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Identity\Models\Role;
use App\Domains\Platform\Models\Payment;
use App\Domains\Platform\Models\Subscription;
use App\Domains\Platform\Services\AiTriageSummary;
use App\Domains\Platform\Services\PlatformNotifier;
use App\Domains\Platform\Services\TriageEngine;
use App\Domains\Workspace\Models\Client;
use App\Models\User;
use App\Notifications\DailyBriefingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Daily Briefing — صبح هر روز برای مدیر ارشد (مالک) ارسال می‌شود تا بدون
 * ورود به پنل، فقط «تصمیم‌های واقعی» و خلاصهٔ روز قبل را ببیند (سند ۳۷).
 */
class SendDailyBriefing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function handle(): void
    {
        $this->triage = app(TriageEngine::class);

        $superAdminRole = Role::where('key', 'super-admin')->first();
        if ($superAdminRole === null) {
            return;
        }

        $recipients = User::query()
            ->whereHas('memberships', fn ($q) => $q->where('role_id', $superAdminRole->getKey())->where('status', 'active'))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $summary = $this->buildSummary();
        $summary['triage_ai'] = app(AiTriageSummary::class)->summarize($summary['pending_decisions']);

        foreach ($recipients as $user) {
            $user->notify(new DailyBriefingNotification($summary));
        }

        // کانال فعال: تلگرام (اگر پیکربندی شده باشد)
        $notifier = app(PlatformNotifier::class);
        $notifier->notify(
            sprintf(
                '<b>گزارش صبح سوئیت</b> — %s سازمان فعال · %d تصمیم در انتظار · %d استثنای باز',
                (string) ($summary['kpis']['orgs_active'] ?? 0),
                count($summary['pending_decisions'] ?? []),
                count($summary['open_exceptions'] ?? []),
            ),
            count($summary['pending_decisions'] ?? []) > 0 ? 'warning' : 'info',
        );

        app(RecordAuditLog::class)->handle(
            action: 'schedule.executed',
            after: ['job' => 'SendDailyBriefing', 'recipients' => $recipients->count()],
            organization: null,
            source: 'schedule',
        );
    }

    private TriageEngine $triage;

    /** @return array<string, mixed> */
    private function buildSummary(): array
    {
        $decisions = $this->triage->pendingDecisions(8);
        $exceptions = $this->triage->unresolvedExceptions(8);

        $yesterday = now()->yesterday();
        $yesterdayMetrics = DB::table('platform_metrics')->where('date', $yesterday->toDateString())->first();

        $revenueMonth = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('amount');

        return [
            'date' => now()->toDateString(),
            'kpis' => [
                'orgs_active' => Subscription::query()
                    ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
                    ->distinct('organization_id')
                    ->count('organization_id'),
                'clients_total' => Client::query()->count(),
                'sites_connected' => DB::table('site_connections')->whereIn('status', ['paired', 'connected'])->distinct('site_id')->count('site_id'),
                'revenue_month' => (int) $revenueMonth,
                'commands_executed_yesterday' => (int) ($yesterdayMetrics->commands_executed ?? 0),
            ],
            'pending_decisions' => array_map(fn (array $d): array => [
                'type' => $d['type'],
                'severity' => $d['severity'],
                'payload' => $d['payload'],
            ], $decisions),
            'open_exceptions' => array_map(fn (array $e): array => [
                'type' => $e['type'],
                'severity' => $e['severity'],
                'payload' => $e['payload'],
            ], $exceptions),
            'top_actions' => $this->topActions($decisions),
        ];
    }

    /** @param  array<int, array<string, mixed>>  $decisions
     * @return array<int, array<string, string>>
     */
    private function topActions(array $decisions): array
    {
        return array_slice(array_map(fn (array $d): array => [
            'type' => (string) $d['type'],
            'hint' => $this->actionHint((string) $d['type']),
        ], $decisions), 0, 3);
    }

    private function actionHint(string $type): string
    {
        return match ($type) {
            'payment.failed' => 'پرداخت ناموفق — یادآوری یا تعلیق را تصمیم بگیرید.',
            'review.awaiting' => 'پیشنویس‌های در انتظار بررسی — از صف تصمیم اقدام کنید.',
            'command.awaiting' => 'دستورهای در انتظار تأیید — در صف تصمیم ببینید.',
            'subscription.expiring' => 'اشتراک در حال انقضا — تمدید یا لغو را بررسی کنید.',
            'default' => 'در صف تصمیم پلتفرم ببینید.',
        };
    }
}
