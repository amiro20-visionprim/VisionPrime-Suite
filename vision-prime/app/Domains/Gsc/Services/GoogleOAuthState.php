<?php

declare(strict_types=1);

namespace App\Domains\Gsc\Services;

use Illuminate\Support\Str;

class GoogleOAuthState
{
    public function create(int $organizationId): string
    {
        $state = (string) Str::uuid();
        session(['gsc_oauth_state' => $state, 'gsc_oauth_org' => $organizationId]);

        return $state;
    }

    public function validate(string $state, int $organizationId): bool
    {
        return hash_equals((string) session('gsc_oauth_state'), $state) && (int) session('gsc_oauth_org') === $organizationId;
    }
}
