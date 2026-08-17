<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use Illuminate\Support\Facades\Http;

/**
 * اعلان فعال پلتفرم (سند ۳۷ — کانال فعال، تلگرام اول).
 *
 * استثناها و تصمیم‌های Triage به تلگرام مالک ارسال می‌شوند تا بدون باز کردن
 * پنل، از رویدادهای مهم باخبر شود. بدون توکن/چت‌آیدی → بی‌صدا fallback
 * (فقط database) — هیچ‌وقت crash نمی‌کند.
 */
class PlatformNotifier
{
    public function notify(string $message, string $severity = 'info'): bool
    {
        $botToken = (string) config('services.telegram.bot_token', '');
        $chatId = (string) config('services.telegram.chat_id', '');

        if ($botToken === '' || $chatId === '') {
            return false;
        }

        $icon = match ($severity) {
            'critical' => '🔴',
            'warning' => '🟡',
            default => '🟢',
        };

        try {
            $response = Http::timeout(10)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $icon.' '.$message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
