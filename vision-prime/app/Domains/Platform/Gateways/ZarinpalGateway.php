<?php

declare(strict_types=1);

namespace App\Domains\Platform\Gateways;

use App\Domains\Platform\Contracts\PaymentGateway;
use App\Domains\Platform\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * درگاه زرین‌پال (API v4) — واقعی.
 *
 * مبلغ سیستم بر حسب ریال (IRT) است؛ زرین‌پال تومان می‌گیرد، بنابراین
 * در مرز درگاه بر ۱۰ تقسیم می‌شود. در حالت sandbox (بدون merchant_id)
 * به‌صورت شبیه‌سازی‌شده کار می‌کند تا تست و توسعه بدون کلید ممکن باشد.
 */
class ZarinpalGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'zarinpal';
    }

    public function label(): string
    {
        return 'زرین‌پال';
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $merchant = (string) config('services.zarinpal.merchant_id');

        if ($merchant === '') {
            // Sandbox: بدون کلید واقعی — تراکنش شبیه‌سازی‌شده.
            $transactionId = 'zarinpal-sandbox-'.Str::lower(Str::ulid());
            $payment->update([
                'status' => Payment::STATUS_PENDING,
                'gateway_transaction_id' => $transactionId,
            ]);

            return [
                'redirect_url' => route('platform.payments.callback', [
                    'gateway' => 'zarinpal',
                    'transaction' => $transactionId,
                ], absolute: false),
                'transaction_id' => $transactionId,
            ];
        }

        $response = Http::timeout(20)
            ->post('https://payment.zarinpal.com/pg/v4/payment/request.json', [
                'merchant_id' => $merchant,
                'amount' => intdiv($payment->amount, 10),
                'callback_url' => $callbackUrl,
                'description' => "پرداخت اشتراک #{$payment->subscription_id}",
                'metadata' => ['payment_id' => (string) $payment->getKey()],
            ])
            ->throw()
            ->json();

        $authority = (string) ($response['data']['authority'] ?? '');

        if ($authority === '') {
            throw new \RuntimeException('زرین‌پال: پاسخ بدون authority دریافت شد.');
        }

        $payment->update([
            'status' => Payment::STATUS_PENDING,
            'gateway_transaction_id' => $authority,
        ]);

        return [
            'redirect_url' => "https://payment.zarinpal.com/pg/StartPay/{$authority}",
            'transaction_id' => $authority,
        ];
    }

    public function verify(Payment $payment, array $request): bool
    {
        $merchant = (string) config('services.zarinpal.merchant_id');
        $authority = (string) ($request['authority'] ?? $payment->gateway_transaction_id);

        if ($merchant === '') {
            // Sandbox: هر بازگشتی موفق است مگر Status=NOK صریح.
            $ok = ($request['Status'] ?? 'OK') !== 'NOK';
            $payment->update([
                'status' => $ok ? Payment::STATUS_PAID : Payment::STATUS_FAILED,
                'paid_at' => $ok ? now() : null,
            ]);

            return $ok;
        }

        $response = Http::timeout(20)
            ->post('https://payment.zarinpal.com/pg/v4/payment/verify.json', [
                'merchant_id' => $merchant,
                'amount' => intdiv($payment->amount, 10),
                'authority' => $authority,
            ])
            ->throw()
            ->json();

        $ok = (int) ($response['data']['code'] ?? 0) === 100;

        $payment->update([
            'status' => $ok ? Payment::STATUS_PAID : Payment::STATUS_FAILED,
            'paid_at' => $ok ? now() : null,
        ]);

        return $ok;
    }
}
