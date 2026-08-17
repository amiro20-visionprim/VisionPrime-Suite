<?php

declare(strict_types=1);

namespace App\Domains\Platform\Sms;

use App\Domains\Platform\Contracts\SmsSender;
use Illuminate\Support\Facades\Http;

/**
 * پنل پیامکی کاوه‌نگار (kavenegar.com) — واقعی.
 *
 * بدون api_key (sandbox) فقط لاگ می‌شود تا توسعه و تست بدون کلید ممکن باشد.
 */
class KavenegarSms implements SmsSender
{
    public function key(): string
    {
        return 'kavenegar';
    }

    public function label(): string
    {
        return 'کاوه‌نگار';
    }

    public function send(string $to, string $message, ?string $template = null): array
    {
        $apiKey = (string) config('services.kavenegar.api_key');

        if ($apiKey === '') {
            return ['success' => true, 'external_id' => 'sandbox-'.now()->timestamp, 'error' => null];
        }

        $sender = (string) config('services.kavenegar.sender');

        try {
            $response = Http::timeout(15)
                ->post("https://api.kavenegar.com/v1/{$apiKey}/sms/send.json", [
                    'receptor' => $to,
                    'message' => $message,
                    'sender' => $sender,
                ])
                ->throw()
                ->json();

            $entry = $response['entries'][0] ?? [];
            $status = (int) ($entry['status'] ?? 0);

            return [
                'success' => $status === 1 || $status === 5 || $status === 6,
                'external_id' => isset($entry['messageid']) ? (string) $entry['messageid'] : null,
                'error' => $status === 1 || $status === 5 || $status === 6 ? null : "وضعیت نامشخص ({$status})",
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'external_id' => null, 'error' => $e->getMessage()];
        }
    }
}
