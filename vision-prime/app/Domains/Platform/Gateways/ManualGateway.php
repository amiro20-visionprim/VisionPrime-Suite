<?php

declare(strict_types=1);

namespace App\Domains\Platform\Gateways;

use App\Domains\Platform\Contracts\PaymentGateway;
use App\Domains\Platform\Models\Payment;

/**
 * درگاه دستی (واریز بانکی / پرداخت آفلاین) — هیچ درگاه خارجی ندارد؛
 * پرداخت توسط اپراتور پلتفرم تأیید می‌شود (PaymentService::markPaid).
 */
class ManualGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'دستی (واریز بانکی)';
    }

    public function initiate(Payment $payment, string $callbackUrl): array
    {
        throw new \RuntimeException('درگاه دستی تراکنش آنلاین ندارد؛ پرداخت به‌صورت دستی ثبت می‌شود.');
    }

    public function verify(Payment $payment, array $request): bool
    {
        return false;
    }
}
