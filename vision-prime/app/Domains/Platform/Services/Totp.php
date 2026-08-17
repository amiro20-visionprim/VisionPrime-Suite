<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use Illuminate\Support\Str;

/**
 * TOTP پیاده‌سازی RFC 6238 (سازگار با Google Authenticator / Authy) —
 * بدون کتابخانهٔ خارجی، فقط با hash_hmac.
 */
class Totp
{
    private const DIGITS = 6;

    private const PERIOD = 30;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** ساخت سکرت جدید (base32) — برای فعال‌سازی MFA. */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /** کد ۶ رقمی فعلی برای یک سکرت. */
    public function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);
        $bin = pack('N*', 0).pack('N*', $counter);
        $hash = hash_hmac('sha1', $bin, $this->base32Decode($secret), true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** تأیید کد — با پنجرهٔ ۱ (تحمل ±۳۰ ثانیه). */
    public function verify(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $now = time();
        foreach ([-1, 0, 1] as $window) {
            if (hash_equals($this->code($secret, $now + ($window * self::PERIOD)), $code)) {
                return true;
            }
        }

        return false;
    }

    /** otpauth URI برای اسکن QR در اپ احراز هویت. */
    public function otpauthUri(string $secret, string $label): string
    {
        return 'otpauth://totp/'.rawurlencode($label).'?secret='.$secret.'&issuer='.rawurlencode('Vision Prime SUITE').'&algorithm=SHA1&digits=6&period=30';
    }

    private function base32Encode(string $data): string
    {
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            $encoded .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return rtrim($encoded, 'A');
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(trim($secret));
        $binary = '';
        foreach (str_split($secret) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos === false) {
                continue;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }

    /** @return list<string> کدهای پشتیبان یکبارمصرف (8 کاراکتری) */
    public function backupCodes(int $count = 10): array
    {
        return array_map(
            fn (): string => strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)),
            range(1, $count),
        );
    }
}
