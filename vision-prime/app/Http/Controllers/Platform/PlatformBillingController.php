<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domains\Organization\Models\Organization;
use App\Domains\Platform\Models\Invoice;
use App\Domains\Platform\Models\Payment;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\Subscription;
use App\Domains\Platform\Services\InvoiceService;
use App\Domains\Platform\Services\PaymentService;
use App\Domains\Platform\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformBillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PaymentService $payments,
        private readonly InvoiceService $invoices,
    ) {}

    // ─── پلن‌ها ───

    public function plans(): Response
    {
        return Inertia::render('Platform/Plans', [
            'plans' => Plan::query()->withCount('subscriptions')->orderBy('sort')->get()->map(fn (Plan $plan): array => [
                'id' => $plan->getKey(),
                'key' => $plan->key,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'limits' => $plan->limits(),
                'features' => $plan->features(),
                'is_active' => $plan->is_active,
                'subscriptions_count' => (int) $plan->subscriptions_count,
            ])->all(),
        ]);
    }

    public function storePlan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:50', 'unique:plans,key'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'price_yearly' => ['required', 'integer', 'min:0'],
            'max_sites' => ['nullable', 'integer', 'min:0'],
            'max_clients' => ['nullable', 'integer', 'min:0'],
            'max_ai_tokens_monthly' => ['nullable', 'integer', 'min:0'],
            'max_profiles' => ['nullable', 'integer', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'auto_publish' => ['nullable', 'boolean'],
        ]);

        Plan::create([
            'key' => $data['key'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price_monthly' => $data['price_monthly'],
            'price_yearly' => $data['price_yearly'],
            'currency' => 'IRT',
            'limits' => [
                'max_sites' => (int) ($data['max_sites'] ?? 0),
                'max_clients' => (int) ($data['max_clients'] ?? 0),
                'max_ai_tokens_monthly' => (int) ($data['max_ai_tokens_monthly'] ?? 0),
                'max_profiles' => (int) ($data['max_profiles'] ?? 0),
            ],
            'features' => [
                'trial_days' => (int) ($data['trial_days'] ?? 0),
                'auto_publish' => (bool) ($data['auto_publish'] ?? false),
            ],
            'is_active' => true,
            'sort' => Plan::max('sort') + 1,
        ]);

        return back()->with('status', 'پلن ساخته شد.');
    }

    public function togglePlan(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);

        return back()->with('status', $plan->is_active ? 'پلن فعال شد.' : 'پلن بایگانی شد.');
    }

    // ─── اشتراک‌ها ───

    public function subscriptions(): Response
    {
        return Inertia::render('Platform/Subscriptions', [
            'subscriptions' => Subscription::query()
                ->with(['organization', 'plan'])
                ->latest()
                ->get()
                ->map(fn (Subscription $subscription): array => [
                    'id' => $subscription->getKey(),
                    'organization_name' => $subscription->organization?->name ?? '—',
                    'organization_id' => $subscription->organization_id,
                    'plan_name' => $subscription->plan?->name ?? '—',
                    'status' => $subscription->status,
                    'status_label' => $subscription->statusLabel(),
                    'current_period_end' => (string) $subscription->current_period_end,
                    'auto_renew' => $subscription->auto_renew,
                    'cancel_at_period_end' => $subscription->cancel_at_period_end,
                ])->all(),
            'organizations' => Organization::query()->where('status', 'active')->get(['id', 'name'])->map(fn (Organization $org): array => [
                'id' => $org->getKey(),
                'name' => $org->name,
            ])->all(),
            'plans' => Plan::query()->where('is_active', true)->get(['id', 'name'])->map(fn (Plan $plan): array => [
                'id' => $plan->getKey(),
                'name' => $plan->name,
            ])->all(),
        ]);
    }

    public function storeSubscription(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:30'],
        ]);

        $this->subscriptions->activate(
            Organization::findOrFail($data['organization_id']),
            Plan::findOrFail($data['plan_id']),
            isset($data['trial_days']) ? (int) $data['trial_days'] : null,
        );

        return back()->with('status', 'اشتراک ثبت شد.');
    }

    public function subscriptionAction(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:renew,cancel,reactivate,suspend'],
        ]);

        match ($data['action']) {
            'renew' => $this->subscriptions->renew($subscription),
            'cancel' => $this->subscriptions->cancel($subscription),
            'reactivate' => $this->subscriptions->reactivate($subscription),
            'suspend' => $this->subscriptions->suspend($subscription),
        };

        return back()->with('status', 'وضعیت اشتراک بهروز شد.');
    }

    // ─── پرداخت‌ها ───

    public function payments(): Response
    {
        return Inertia::render('Platform/Payments', [
            'payments' => Payment::query()
                ->with('organization')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (Payment $payment): array => [
                    'id' => $payment->getKey(),
                    'organization_name' => $payment->organization?->name ?? '—',
                    'organization_id' => $payment->organization_id,
                    'amount' => $payment->amount,
                    'method' => $payment->method,
                    'status' => $payment->status,
                    'status_label' => $payment->statusLabel(),
                    'reference' => $payment->reference,
                    'paid_at' => (string) $payment->paid_at,
                ])->all(),
            'organizations' => Organization::query()->where('status', 'active')->get(['id', 'name'])->map(fn (Organization $org): array => [
                'id' => $org->getKey(),
                'name' => $org->name,
            ])->all(),
        ]);
    }

    public function storePayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'in:zarinpal,idpay,manual,bank'],
        ]);

        $org = Organization::findOrFail($data['organization_id']);
        $subscription = Subscription::query()->where('organization_id', $org->getKey())->latest()->first();

        $this->payments->recordManual($org, (int) $data['amount'], $subscription, method: (string) $data['method']);

        return back()->with('status', 'پرداخت ثبت شد.');
    }

    public function paymentAction(Payment $payment): RedirectResponse
    {
        if ($payment->status === Payment::STATUS_PENDING) {
            $this->payments->markPaid($payment);
        } elseif ($payment->status === Payment::STATUS_PAID) {
            $this->payments->refund($payment);
        }

        return back()->with('status', 'وضعیت پرداخت بهروز شد.');
    }

    // ─── فاکتورها ───

    public function invoices(): Response
    {
        return Inertia::render('Platform/Invoices', [
            'invoices' => Invoice::query()
                ->with(['organization', 'subscription.plan'])
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->getKey(),
                    'organization_name' => $invoice->organization?->name ?? '—',
                    'plan_name' => $invoice->subscription?->plan?->name ?? '—',
                    'number' => $invoice->number,
                    'amount' => $invoice->amount,
                    'tax' => $invoice->tax,
                    'total' => $invoice->total,
                    'status' => $invoice->status,
                    'status_label' => $invoice->statusLabel(),
                    'due_at' => (string) $invoice->due_at,
                ])->all(),
            'subscriptions' => Subscription::query()
                ->with('organization')
                ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
                ->get()
                ->map(fn (Subscription $subscription): array => [
                    'id' => $subscription->getKey(),
                    'label' => ($subscription->organization?->name ?? '—').' — '.($subscription->plan?->name ?? '—'),
                ])->all(),
        ]);
    }

    public function storeInvoice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subscription_id' => ['required', 'integer', 'exists:subscriptions,id'],
        ]);

        $this->invoices->generateForSubscription(Subscription::findOrFail($data['subscription_id']));

        return back()->with('status', 'فاکتور صادر شد.');
    }

    public function runOverdueCheck(): RedirectResponse
    {
        $count = $this->invoices->overdueCheck();

        return back()->with('status', $count > 0 ? "{$count} فاکتور معوق شد." : 'فاکتور معوقی وجود ندارد.');
    }
}
