<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domains\Platform\Models\Payment;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\Subscription;
use App\Domains\Platform\Services\AiTriageSummary;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDashboardController extends Controller
{
    public function __construct(private readonly AiTriageSummary $triage) {}

    public function __invoke(): Response
    {
        $metrics = DB::table('platform_metrics')
            ->orderBy('date')
            ->limit(30)
            ->get()
            ->map(fn ($row): array => [
                'date' => (string) $row->date,
                'orgs_active' => (int) $row->orgs_active,
                'clients_total' => (int) $row->clients_total,
                'sites_connected' => (int) $row->sites_connected,
                'commands_executed' => (int) $row->commands_executed,
                'tokens_in' => (int) $row->tokens_in,
                'tokens_out' => (int) $row->tokens_out,
            ])->all();

        // KPI زنده (نه فقط rollup) — برای دقت لحظه‌ای
        $kpis = [
            'orgs_active' => Subscription::query()
                ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
                ->distinct('organization_id')
                ->count('organization_id'),
            'orgs_trialing' => Subscription::query()->where('status', Subscription::STATUS_TRIALING)->count(),
            'clients_total' => Client::query()->count(),
            'sites_total' => Site::query()->count(),
            'sites_connected' => DB::table('site_connections')->whereIn('status', ['paired', 'connected'])->distinct('site_id')->count('site_id'),
            'revenue_month' => Payment::query()
                ->where('status', Payment::STATUS_PAID)
                ->where('paid_at', '>=', now()->startOfMonth())
                ->sum('amount'),
            'tokens_month' => DB::table('ai_generations')
                ->where('created_at', '>=', now()->startOfMonth())
                ->get()
                ->sum(fn ($row): int => (int) (json_decode((string) $row->usage, true)['output_tokens'] ?? 0)),
            'reviews_pending' => DB::table('review_items')->whereNull('resolved_at')->count(),
            'plans_count' => Plan::query()->where('is_active', true)->count(),
        ];

        // آخرین رویدادهای کل پلتفرم (بدون CurrentOrganization — بالای orgها)
        $recentEvents = DB::table('audit_logs')
            ->whereIn('action', [
                'platform.subscription.activated',
                'platform.subscription.renewed',
                'platform.subscription.suspended',
                'platform.payment.recorded',
                'platform.payment.refunded',
                'platform.invoice.issued',
                'platform.invoice.overdue_batch',
                'automation.emergency_stop_activated',
                'ai.article_draft_generated',
                'ai.draft_generated',
                'client.question.created',
            ])
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'action' => (string) $row->action,
                'organization_id' => $row->organization_id,
                'actor_id' => $row->actor_id,
                'occurred_at' => (string) $row->occurred_at,
            ])->all();

        $eventLabels = [
            'platform.subscription.activated' => 'اشتراک جدید فعال شد',
            'platform.subscription.renewed' => 'اشتراک تمدید شد',
            'platform.subscription.suspended' => 'اشتراک معلق شد',
            'platform.payment.recorded' => 'پرداخت ثبت شد',
            'platform.payment.refunded' => 'پرداخت بازگشت داده شد',
            'platform.invoice.issued' => 'فاکتور صادر شد',
            'platform.invoice.overdue_batch' => 'فاکتور معوق شناسایی شد',
            'automation.emergency_stop_activated' => 'توقف اضطراری فعال شد',
            'ai.article_draft_generated' => 'پیش‌نویس مقاله تولید شد',
            'ai.draft_generated' => 'پیش‌نویس AI تولید شد',
            'client.question.created' => 'سؤال جدید از مشتری',
        ];

        // صف تصمیم (E1-03): رویدادهای decision حل‌نشده
        $pendingDecisions = DB::table('platform_events')
            ->where('triage', 'decision')
            ->whereNull('resolved_at')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'severity' => (string) $row->severity,
                'payload' => json_decode((string) $row->payload, true) ?? [],
                'organization_id' => $row->organization_id,
                'created_at' => (string) $row->created_at,
            ])->all();

        $triageSummary = $this->triage->summarize($pendingDecisions);

        return Inertia::render('Platform/Dashboard', [
            'kpis' => $kpis,
            'trend' => $metrics,
            'recentEvents' => $recentEvents,
            'eventLabels' => $eventLabels,
            'pendingDecisions' => $pendingDecisions,
            'triageSummary' => $triageSummary,
        ]);
    }
}
