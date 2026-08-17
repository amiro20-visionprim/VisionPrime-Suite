<?php

declare(strict_types=1);

namespace App\Domains\Platform\Contracts;

use App\Domains\Platform\Models\Payment;

/**
 * قرارداد درگاه پرداخت — هر درایور (زرین‌پال، آقای پرداخت، دستی، ...) باید
 * این چهار متد را پیاده کند تا بدون تغییر در لایهٔ بالاتر قابل افزودن باشد.
 */
interface PaymentGateway
{
    /** نام کلید درایور (zarinpal / aqayepardakht / manual / ...). */
    public function key(): string;

    /** نام فارسی نمایشی درگاه. */
    public function label(): string;

    /**
     * شروع پرداخت: تراکنش را در درگاه می‌سازد و Payment را pending می‌کند.
     *
     * @return array{redirect_url: string, transaction_id: string}
     */
    public function initiate(Payment $payment, string $callbackUrl): array;

    /**
     * تأیید پرداخت پس از بازگشت کاربر از درگاه.
     * در صورت موفقیت Payment را paid و در غیر این صورت failed می‌کند.
     */
    public function verify(Payment $payment, array $request): bool;
}
