<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Contracts\SmsSender;
use App\Domains\Platform\Sms\KavenegarSms;
use Illuminate\Support\Facades\DB;

/**
 * مدیر پیامک — درایورها اینجا ثبت می‌شوند؛ هر ارسال در sms_logs ثبت می‌شود.
 * افزودن پنل جدید = یک کلاس درایور + یک خط رجیستر.
 */
class SmsManager
{
    /** @var array<string, SmsSender> */
    private array $drivers = [];

    public function __construct()
    {
        foreach ($this->registered() as $driver) {
            $this->drivers[$driver->key()] = $driver;
        }
    }

    /** @return list<SmsSender> */
    public function registered(): array
    {
        return [new KavenegarSms];
    }

    public function all(): array
    {
        return array_values($this->drivers);
    }

    public function get(string $key): SmsSender
    {
        return $this->drivers[$key] ?? throw new \RuntimeException("پنل پیامکی ناشناخته: {$key}");
    }

    /**
     * ارسال پیامک + ثبت در sms_logs.
     *
     * @return array{success: bool, external_id?: string|null, error?: string|null}
     */
    public function send(string $to, string $message, string $driverKey = 'kavenegar'): array
    {
        $driver = $this->get($driverKey);
        $result = $driver->send($to, $message);

        DB::table('sms_logs')->insert([
            'driver' => $driverKey,
            'to' => $to,
            'message' => $message,
            'status' => $result['success'] ? 'sent' : 'failed',
            'external_id' => $result['external_id'] ?? null,
            'error' => $result['error'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $result;
    }

    /** @return array<int, array{key: string, label: string}> */
    public function options(): array
    {
        return array_map(
            static fn (SmsSender $s): array => ['key' => $s->key(), 'label' => $s->label()],
            $this->all(),
        );
    }
}
