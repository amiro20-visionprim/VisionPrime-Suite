<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Services;

use Illuminate\Validation\ValidationException;

class CanonicalUrl
{
    public function normalize(string $value): string
    {
        $url = trim($value);
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'],$parts['host']) || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw ValidationException::withMessages(['canonical_url' => 'نشانی سایت باید یک URL معتبر با http یا https باشد.']);
        } $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) && ! (($scheme === 'https' && $parts['port'] === 443) || ($scheme === 'http' && $parts['port'] === 80)) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        return $scheme.'://'.$host.$port.($path === '' ? '' : $path);
    }
}
