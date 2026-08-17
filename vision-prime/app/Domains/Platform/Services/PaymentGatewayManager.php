<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Contracts\PaymentGateway;
use App\Domains\Platform\Gateways\AqayepardakhtGateway;
use App\Domains\Platform\Gateways\ManualGateway;
use App\Domains\Platform\Gateways\ZarinpalGateway;

/**
 * مدیر درگاه‌ها — همهٔ درایورها اینجا ثبت می‌شوند و انتخاب بر اساس
 * کلید (zarinpal / aqayepardakht / manual / ...) انجام می‌شود.
 * افزودن درگاه جدید = یک کلاس درایور + یک خط رجیستر.
 */
class PaymentGatewayManager
{
    /** @var array<string, PaymentGateway> */
    private array $drivers = [];

    public function __construct()
    {
        foreach ($this->registered() as $driver) {
            $this->drivers[$driver->key()] = $driver;
        }
    }

    /** @return list<PaymentGateway> */
    public function registered(): array
    {
        return [
            new ZarinpalGateway,
            new AqayepardakhtGateway,
            new ManualGateway,
        ];
    }

    public function all(): array
    {
        return array_values($this->drivers);
    }

    public function get(string $key): PaymentGateway
    {
        return $this->drivers[$key] ?? throw new \RuntimeException("درگاه ناشناخته: {$key}");
    }

    /** @return array<int, array{key: string, label: string}> */
    public function options(): array
    {
        return array_map(
            static fn (PaymentGateway $g): array => ['key' => $g->key(), 'label' => $g->label()],
            $this->all(),
        );
    }
}
