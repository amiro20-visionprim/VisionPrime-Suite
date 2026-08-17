<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\Subscription;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlatformOperationsController extends Controller
{
    /** نام jobهایی که در scheduler تعریف شده‌اند */
    private const SCHEDULED_JOBS = [
        'gsc:import' => 'واردکردن دادهٔ سرچ کنسول',
        'LearningLoop' => 'حلقهٔ یادگیری اتوماسیون',
        'RollbackMonitor' => 'مانیتور بازگشت خودکار',
        'ReleaseScheduledCommands' => 'آزادسازی زمان‌بندی‌شده',
        'RemindScheduledPublishes' => 'یادآوری انتشار',
        'ProcessQueuedCommands' => 'پردازش صف دستورها',
        'CollectPlatformEvents' => 'حسگر رویدادهای پلتفرم',
        'SendDailyBriefing' => 'گزارش روزانهٔ هوشمند',
    ];

    public function __invoke(): Response
    {
        // سلامت صف
        $queue = [
            'pending' => DB::table('jobs')->count(),
            'failed_24h' => DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count(),
            'failed_total' => DB::table('failed_jobs')->count(),
        ];

        // آخرین اجرای هر job از audit_logs
        $lastRuns = DB::table('audit_logs')
            ->where('source', 'schedule')
            ->whereIn('action', ['job.executed', 'schedule.executed'])
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get()
            ->groupBy(fn ($row): string => (string) $row->metadata);

        $scheduler = [];
        foreach (self::SCHEDULED_JOBS as $job => $label) {
            $latest = DB::table('audit_logs')
                ->where('action', 'like', '%'.str_replace(':', '', $job).'%')
                ->orderByDesc('occurred_at')
                ->first();
            $scheduler[] = [
                'job' => $job,
                'label' => $label,
                'last_run' => $latest?->occurred_at ? (string) $latest->occurred_at : null,
            ];
        }

        // مصرف AI سراسری per-org با سقف پلن
        $usageBySite = DB::table('ai_generations')
            ->where('created_at', '>=', now()->startOfMonth())
            ->get()
            ->groupBy('site_id')
            ->map(fn ($rows): int => $rows->sum(fn ($row): int => (int) (json_decode((string) $row->usage, true)['output_tokens'] ?? 0)));

        $siteOrgs = DB::table('sites')->pluck('organization_id', 'id');

        $aiUsage = Subscription::query()
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->with('plan')
            ->get()
            ->map(function (Subscription $subscription) use ($usageBySite, $siteOrgs): array {
                $orgId = (int) $subscription->organization_id;
                $siteIds = $siteOrgs->filter(fn ($org): bool => $org === $orgId)->keys();
                $tokens = $siteIds->sum(fn ($siteId): int => (int) ($usageBySite->get((int) $siteId) ?? 0));
                $cap = (int) ($subscription->plan?->limits()['max_ai_tokens_monthly'] ?? 0);

                return [
                    'organization_id' => $orgId,
                    'organization_name' => $subscription->organization?->name ?? "سازمان #{$orgId}",
                    'plan' => $subscription->plan?->name ?? '—',
                    'tokens' => $tokens,
                    'cap' => $cap,
                    'usage_percent' => $cap > 0 ? (int) round(($tokens / $cap) * 100) : 0,
                ];
            })->values()->all();

        // وضعیت اتصالها
        $connections = [
            'paired' => DB::table('site_connections')->whereIn('status', ['paired', 'connected'])->count(),
            'unpaired' => DB::table('site_connections')->where('status', 'unpaired')->count(),
            'sites_without_connection' => DB::table('sites')->whereNotIn('id', DB::table('site_connections')->whereIn('status', ['paired', 'connected'])->pluck('site_id'))->count(),
        ];

        return Inertia::render('Platform/Operations', [
            'queue' => $queue,
            'scheduler' => $scheduler,
            'aiUsage' => $aiUsage,
            'connections' => $connections,
            'plansCount' => Plan::query()->where('is_active', true)->count(),
        ]);
    }
}
