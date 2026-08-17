<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Organization\Models\Organization;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\Subscription;
use App\Domains\Platform\Services\PaymentService;
use App\Domains\Platform\Services\SubscriptionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformBillingSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'key' => 'starter',
                'name' => 'استارتاپ',
                'description' => 'برای شروع: یک سایت، مشتری محدود و تولید محتوای پایه.',
                'price_monthly' => 1_200_000,
                'price_yearly' => 12_000_000,
                'limits' => ['max_sites' => 1, 'max_clients' => 1, 'max_ai_tokens_monthly' => 500_000, 'max_profiles' => 2],
                'features' => ['trial_days' => 7, 'auto_publish' => false, 'gsc' => true],
                'sort' => 1,
            ],
            [
                'key' => 'growth',
                'name' => 'رشد',
                'description' => 'برای آژانس‌های فعال: چند سایت، اتوماسیون و انتشار خودکار.',
                'price_monthly' => 3_500_000,
                'price_yearly' => 35_000_000,
                'limits' => ['max_sites' => 5, 'max_clients' => 10, 'max_ai_tokens_monthly' => 2_000_000, 'max_profiles' => 6],
                'features' => ['trial_days' => 14, 'auto_publish' => true, 'gsc' => true],
                'sort' => 2,
            ],
            [
                'key' => 'enterprise',
                'name' => 'سازمانی',
                'description' => 'برای تیم‌های بزرگ: سایت نامحدود، پشتیبانی اختصاصی و SLA.',
                'price_monthly' => 9_500_000,
                'price_yearly' => 95_000_000,
                'limits' => ['max_sites' => 50, 'max_clients' => 100, 'max_ai_tokens_monthly' => 10_000_000, 'max_profiles' => 20],
                'features' => ['trial_days' => 14, 'auto_publish' => true, 'gsc' => true, 'dedicated_support' => true],
                'sort' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(['key' => $plan['key']], $plan);
        }

        $growth = Plan::where('key', 'growth')->firstOrFail();

        $org = Organization::where('slug', 'vision-prime-demo')->first();
        if ($org === null) {
            return;
        }

        $subscriptionService = app(SubscriptionService::class);
        $paymentService = app(PaymentService::class);

        $existing = Subscription::query()
            ->where('organization_id', $org->id)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->first();

        if ($existing !== null) {
            return;
        }

        $subscription = $subscriptionService->activate($org, $growth, trialDays: 0);

        $paymentService->recordManual($org, $growth->price_monthly, $subscription, method: 'bank');

        DB::table('invoices')->insert([
            'organization_id' => $org->id,
            'subscription_id' => $subscription->getKey(),
            'number' => 'INV-20260817-'.strtoupper(substr(uniqid('', true), -6)),
            'amount' => $growth->price_monthly,
            'tax' => (int) round($growth->price_monthly * 0.09),
            'total' => $growth->price_monthly + (int) round($growth->price_monthly * 0.09),
            'status' => 'paid',
            'issued_at' => now()->subDays(2),
            'due_at' => now()->addDays(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ۱۴ روز platform_metrics برای چارت داشبورد
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            DB::table('platform_metrics')->updateOrInsert(
                ['date' => $date],
                [
                    'orgs_active' => 1 + ($i % 3),
                    'orgs_trialing' => 2,
                    'clients_total' => 1 + ($i % 4),
                    'sites_total' => 2 + ($i % 3),
                    'sites_connected' => 1 + ($i % 3),
                    'tokens_in' => 100_000 + $i * 7_000,
                    'tokens_out' => 80_000 + $i * 5_000,
                    'ai_cost' => 0.2 + $i * 0.01,
                    'commands_executed' => 2 + ($i % 5),
                    'commands_rolled_back' => $i % 2,
                    'reviews_pending' => 1 + ($i % 3),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
