<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domains\Platform\Models\Payment;
use App\Domains\Platform\Models\Subscription;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class PlatformReportController extends Controller
{
    public function index(): Response
    {
        // درآمد ماهانه (۱۲ ماه اخیر)
        $revenueByMonth = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', now()->subMonths(12)->startOfMonth())
            ->get()
            ->groupBy(fn (Payment $payment): string => $payment->paid_at->format('Y-m'))
            ->map(fn ($rows): int => (int) $rows->sum('amount'));

        // رشد سازمان‌ها per week
        $newOrgsPerWeek = DB::table('organizations')
            ->where('created_at', '>=', now()->subMonths(3))
            ->get()
            ->groupBy(fn ($row): string => Carbon::parse((string) $row->created_at)->format('Y-W'))
            ->map(fn ($rows): int => count($rows));

        return Inertia::render('Platform/Reports', [
            'revenueByMonth' => $revenueByMonth->map(fn ($total, $month): array => [
                'label' => $month,
                'value' => $total,
            ])->values()->all(),
            'newOrgsPerWeek' => $newOrgsPerWeek->map(fn ($count, $week): array => [
                'label' => $week,
                'value' => $count,
            ])->values()->all(),
            'summary' => [
                'revenue_year' => (int) $revenueByMonth->sum(),
                'orgs_active' => Subscription::query()
                    ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
                    ->distinct('organization_id')
                    ->count('organization_id'),
                'clients_total' => Client::query()->count(),
                'sites_connected' => DB::table('site_connections')->whereIn('status', ['paired', 'connected'])->distinct('site_id')->count('site_id'),
                'sites_total' => Site::query()->count(),
            ],
        ]);
    }

    public function exportCsv(): HttpResponse
    {
        $rows = Payment::query()
            ->with('organization')
            ->orderByDesc('paid_at')
            ->limit(1000)
            ->get()
            ->map(fn (Payment $payment): array => [
                'organization' => $payment->organization?->name ?? '—',
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                'method' => $payment->method,
                'status' => $payment->status,
                'reference' => $payment->reference,
                'paid_at' => (string) $payment->paid_at,
            ])->all();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['سازمان', 'مبلغ', 'ارز', 'روش', 'وضعیت', 'مرجع', 'تاریخ']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return new HttpResponse("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'platform-payments-'.now()->format('Y-m-d').'.csv',
            ),
        ]);
    }
}
