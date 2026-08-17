<?php

declare(strict_types=1);

namespace App\Domains\Platform\Contracts;

/**
 * قرارداد ارسال پیامک — هر پنل پیامکی (کاوه‌نگار، sms.ir، ملی پیامک، ...)
 * با پیاده‌سازی این اینترفیس قابل افزودن است بدون تغییر در لایهٔ بالاتر.
 */
interface SmsSender
{
    /** نام کلید درایور (kavenegar / sms_ir / ...). */
    public function key(): string;

    /** نام فارسی پنل. */
    public function label(): string;

    /**
     * ارسال پیامک ساده.
     *
     * @return array{success: bool, external_id?: string, error?: string}
     */
    public function send(string $to, string $message, ?string $template = null): array;
}
