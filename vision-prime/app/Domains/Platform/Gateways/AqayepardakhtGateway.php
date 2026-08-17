<?php

declare(strict_types=1);

namespace App\Domains\Platform\Gateways;

use App\Domains\Platform\Contracts\PaymentGateway;
use App\Domains\Platform\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * درگاه آقای پرداخت (aqayepardakht.ir) — واقعی.
 *
 * مبلغ سیستم بر حسب ریال (IRT) است؛ آقای پرداخت تومان می‌گیرد، بنابراین
 * در مرز درگاه بر ۱۰ تقسیم می‌شود. بدون pin (sandbox) شبیه‌سازی می‌شود.
 */
class AqayepardakhtGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'aqayepardakht';
    }

    public function label(): string
    {
        return 'آقای پرداخت';
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        $pin = (string) config('services.aqayepardakht.pin');

        if ($pin === '') {
            $transactionId = 'aqp-sandbox-'.Str::lower(Str::ulid());
            $payment->update([
                'status' => Payment::STATUS_PENDING,
                'gateway_transaction_id' => $transactionId,
            ]);

            return [
                'redirect_url' => route('platform.payments.callback', [
                    'gateway' => 'aqayepardakht',
                    'transaction' => $transactionId,
                ], absolute: false),
                'transaction_id' => $transactionId,
            ];
        }

        $response = Http::timeout(20)
            ->post('https://payment.aqayepardakht.ir/api/v1/payment/create', [
                'pin' => $pin,
                'amount' => intdiv($payment->amount, 10),
                'callback' => $callbackUrl,
                'invoice_id' => (string) $payment->getKey(),
                'description' => "پرداخت اشتراک #{$payment->subscription_id}",
            ])
            ->throw()
            ->json();

        $transactionId = (string) ($response['trans_id'] ?? '');

        if ($transactionId === '') {
            throw new \RuntimeException('آقای پرداخت: پاسخ بدون trans_id دریافت شد.');
        }

        $payment->update([
            'status' => Payment::STATUS_PENDING,
            'gateway_transaction_id' => $transactionId,
        ]);

        return [
            'redirect_url' => "https://payment.aqayepardakht.ir/startpay/{$transactionId}",
            'transaction_id' => $transactionId,
        ];
    }

    public function verify(Payment $payment, array $request): bool
    {
        $pin = (string) config('services.aqayepardakht.pin');

        if ($pin === '') {
            $ok = ($request['status'] ?? '1') === '1';
            $payment->update([
                'status' => $ok ? Payment::STATUS_PAID : Payment::STATUS_FAILED,
                'paid_at' => $ok ? now() : null,
            ]);

            return $ok;
        }

        $response = Http::timeout(20)
            ->post('https://payment.aqayepardakht.ir/api/v1/payment/verify', [
                'pin' => $pin,
                'trans_id' => (string) ($request['trans_id'] ?? $payment->gateway_transaction_id),
                'amount' => intdiv($payment->amount, 10),
            ])
            ->throw()
            ->json();

        $ok = (int) ($response['status'] ?? 0) === 1;

        $payment->update([
            'status' => $ok ? Payment::STATUS_PAID : Payment::STATUS_FAILED,
            'paid_at' => $ok ? now() : null,
        ]);

        return $ok;
    }
}
