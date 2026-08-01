<?php

declare(strict_types=1);

namespace App\Domains\Gsc\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class SearchConsoleClient
{
    public function properties(object $account): array
    {
        $token = json_decode(Crypt::decryptString($account->token_ciphertext), true);
        $response = Http::withToken($token['access_token'])->get('https://www.googleapis.com/webmasters/v3/sites')->throw()->json();

        return $response['siteEntry'] ?? [];
    }
}
