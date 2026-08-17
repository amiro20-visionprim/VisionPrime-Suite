<?php

declare(strict_types=1);

namespace App\Domains\Platform\Jobs;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Identity\Models\Role;
use App\Domains\Platform\Models\Invoice;
use App\Domains\Platform\Models\Payment;
use App\Domains\Platform\Models\Subscription;
use App\Domains\Platform\Services\PlatformNotifier;
use App\Domains\Platform\Services\SmsManager;
use App\Domains\Platform\Services\TriageEngine;
use App\Domains\Workspace\Models\Client;
use App\Models\User;
use App\Notifications\WeeklyReportNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * گزارش هفتگی — هر جمعه صبح برای مالک (مدیر ارشد) ارسال می‌شود:
 * خلاصهٔ مالی، سلامت اکوسیستم و تصمیم‌های باز؛ از طریق ایمیل + تلگرام + پیامک.
 */
class SendWeeklyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function handle(): void
    {
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

        $report = $this->buildReport();

        foreach ($recipients as $user) {
            $user->notify(new WeeklyReportNotification($report));
        }

        // کانال فعال: تلگرام (اگر پیکربندی شده باشد)
        app(PlatformNotifier::class)->notify(
            $this->telegramMessage($report),
            count($report['decisions']) > 0 ? 'warning' : 'info',
        );

        // پیامک به مالک (فقط اگر شماره در config باشد — بی‌صدا رد می‌شود)
        $ownerPhone = (string) config('services.sms.owner_phone', '');
        if ($ownerPhone !== '') {
            app(SmsManager::class)->send($ownerPhone, $this->smsMessage($report));
        }

        app(RecordAuditLog::class)->handle(
            action: 'schedule.executed',
            after: ['job' => 'SendWeeklyReport', 'recipients' => $recipients->count()],
            organization: null,
            source: 'schedule',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(): array
    {
        $weekStart = now()->startOfWeek(Carbon::SATURDAY);
        $monthStart = now()->startOfMonth();

        $revenueWeek = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', $weekStart)
            ->sum('amount');

        $revenueMonth = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', $monthStart)
            ->sum('amount');

        $outstanding = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_OVERDUE])
            ->get();

        $metrics = DB::table('platform_metrics')
            ->where('date', '>=', $weekStart->toDateString())
            ->get();

        $triage = app(TriageEngine::class);
        $decisions = $triage->pendingDecisions(10);
        $exceptions = $triage->unresolvedExceptions(10);

        return [
            'week' => sprintf('%s تا %s', $weekStart->format('Y-m-d'), now()->format('Y-m-d')),
            'financial' => [
                'revenue_week' => (int) $revenueWeek,
                'revenue_month' => (int) $revenueMonth,
                'invoices_outstanding' => $outstanding->count(),
                'invoices_outstanding_amount' => (int) $outstanding->sum('total'),
                'invoices_overdue' => Invoice::query()->where('status', Invoice::STATUS_OVERDUE)->count(),
            ],
            'health' => [
                'orgs_active' => Subscription::query()
                    ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
                    ->distinct('organization_id')
                    ->count('organization_id'),
                'clients_total' => Client::query()->count(),
                'sites_connected' => DB::table('site_connections')->whereIn('status', ['paired', 'connected'])->distinct('site_id')->count('site_id'),
                'commands_executed_week' => (int) $metrics->sum('commands_executed'),
                'ai_cost_week' => (float) $metrics->sum('ai_cost'),
                'exceptions_open' => count($exceptions),
            ],
            'decisions' => array_map(fn (array $d): array => [
                'type' => $d['type'],
                'severity' => $d['severity'],
                'payload' => $d['payload'],
            ], $decisions),
        ];
    }

    /** @param  array<string, mixed>  $report */
    private function telegramMessage(array $report): string
    {
        $f = $report['financial'];
        $h = $report['health'];

        $lines = [
            '<b>📊 گزارش هفتگی سوئیت</b>',
            '📅 '.$report['week'],
            '',
            '💰 <b>مالی:</b>',
            '• درآمد این هفته: '.number_format((int) $f['revenue_week']).' تومان',
            '• درآمد این ماه: '.number_format((int) $f['revenue_month']).' تومان',
            '• فاکتور باز: '.$f['invoices_outstanding'].' ('.number_format((int) $f['invoices_outstanding_amount']).' تومان)',
            '• معوق: '.$f['invoices_overdue'],
            '',
            '🛡️ <b>سلامت:</b>',
            '• سازمان فعال: '.$h['orgs_active'].' · سایت متصل: '.$h['sites_connected'],
            '• دستور اجراشده این هفته: '.$h['commands_executed_week'],
            '• استثنای باز: '.$h['exceptions_open'],
        ];

        $decisions = count($report['decisions']);
        $lines[] = '';
        $lines[] = '🔴 تصمیم در انتظار: '.$decisions;

        return implode("\n", $lines);
    }

    /** @param  array<string, mixed>  $report */
    private function smsMessage(array $report): string
    {
        $f = $report['financial'];
        $h = $report['health'];

        return sprintf(
            'گزارش هفتگی سوئیت — درآمد هفته: %s تومان · فاکتور باز: %d · سازمان فعال: %d · تصمیم در انتظار: %d',
            number_format((int) $f['revenue_week']),
            (int) $f['invoices_outstanding'],
            (int) $h['orgs_active'],
            count($report['decisions']),
        );
    }
}
