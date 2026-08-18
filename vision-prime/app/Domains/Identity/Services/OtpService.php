<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Platform\Services\SmsManager;
use Illuminate\Support\Facades\Cache;

/**
 * سرویس کد یکبارمصرف (OTP) برای ورود و ثبت‌نام بدون رمز.
 *
 * کد ۶ رقمی به‌صورت هش‌شده در cache ذخیره می‌شود (۵ دقیقه عمر) و ارسال
 * از طریق پنل پیامکی (کاوه‌نگار) یا تماس انجام می‌شود. تلاش‌های ناموفق
 * محدود است تا از حملات brute-force جلوگیری شود.
 */
class OtpService
{
    public const TTL_SECONDS = 300;

    public function __construct(private readonly SmsManager $sms) {}

    public static function cacheKey(string $phone, string $purpose = 'login'): string
    {
        return "otp:{$purpose}:{$phone}";
    }

    /**
     * تولید و ارسال کد. در حالت sandbox (بدون api_key) کد در لاگ پیامک ثبت
     * می‌شود و مقدار واقعی برای توسعه برمی‌گردد تا تست بدون کلید ممکن باشد.
     *
     * @return array{code?: string, sent: bool, message: string}
     */
    public function request(string $phone, string $purpose = 'login'): array
    {
        $key = self::cacheKey($phone, $purpose);

        if (Cache::has("{$key}:sent_at")) {
            $lastSentAt = (int) (Cache::get("{$key}:sent_at") ?? 0);

            if (now()->timestamp - $lastSentAt < 60) {
                return ['sent' => false, 'message' => 'لطفاً ۶۰ ثانیه بعد از ارسال قبلی صبر کنید.'];
            }
        }

        $code = (string) random_int(100000, 999999);
        Cache::put($key, [
            'code' => hash('sha256', $code),
            'attempts' => 0,
            'purpose' => $purpose,
        ], self::TTL_SECONDS);
        Cache::put("{$key}:sent_at", now()->timestamp, self::TTL_SECONDS);

        $isSandbox = (string) config('services.kavenegar.api_key', '') === '';
        $message = "کد ورود شما به Vision Prime SUITE: {$code} (معتبر تا ۵ دقیقه)";

        if ($purpose === 'register') {
            $message = "کد تأیید شماره تماس شما در Vision Prime SUITE: {$code} (معتبر تا ۵ دقیقه)";
        }

        $result = $this->sms->send($phone, $message);

        return [
            // در sandbox کد واقعی برمی‌گردد تا توسعه بدون کلید ممکن باشد.
            'code' => $isSandbox ? $code : null,
            'sent' => $result['success'],
            'message' => $result['success']
                ? 'کد تأیید ارسال شد.'
                : 'ارسال پیامک ممکن نشد؛ لطفاً دوباره تلاش کنید.',
        ];
    }

    /**
     * بررسی کد ورودی. در صورت موفقیت cache پاک می‌شود.
     */
    public function verify(string $phone, string $code, string $purpose = 'login'): bool
    {
        $key = self::cacheKey($phone, $purpose);
        $stored = Cache::get($key);

        if (! is_array($stored) || ! isset($stored['code'])) {
            return false;
        }

        $attempts = (int) ($stored['attempts'] ?? 0);
        if ($attempts >= 5) {
            Cache::forget($key);

            return false;
        }

        $valid = hash_equals((string) $stored['code'], hash('sha256', trim($code)));
        if ($valid) {
            Cache::forget($key);
            Cache::forget("{$key}:sent_at");

            return true;
        }

        $stored['attempts'] = $attempts + 1;
        Cache::put($key, $stored, self::TTL_SECONDS);

        return false;
    }

    /** نرمال‌سازی شماره تماس ایران (+98 / 09 / 9). */
    public static function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        $phone = preg_replace('/[^0-9+]/', '', $phone) ?? $phone;
        $phone = str_replace('+98', '0', $phone);

        if (str_starts_with($phone, '98') && strlen($phone) === 12) {
            $phone = '0'.substr($phone, 2);
        }

        if (str_starts_with($phone, '9') && strlen($phone) === 10) {
            $phone = '0'.$phone;
        }

        if (str_starts_with($phone, '+989')) {
            $phone = '0'.substr($phone, 3);
        }

        return $phone;
    }
}
