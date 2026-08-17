<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\PlatformSetting;

/**
 * تنظیمات سطح پلتفرم (key/value در جدول platform_settings).
 * مثال: mfa_required — آیا همهٔ مدیران ارشد باید MFA داشته باشند؟
 */
class PlatformSettingsService
{
    /** @var array<string, PlatformSetting|null> */
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->row($key);

        return $row !== null ? $row->value : $default;
    }

    public function set(string $key, string|int|bool|null $value): void
    {
        PlatformSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);

        $this->cache[$key] = null;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return $value === true || $value === 'true' || $value === '1' || $value === 1;
    }

    private function row(string $key): ?PlatformSetting
    {
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = PlatformSetting::where('key', $key)->first();
        }

        return $this->cache[$key];
    }
}
