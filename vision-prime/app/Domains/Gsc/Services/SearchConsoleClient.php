<?php

declare(strict_types=1);

namespace App\Domains\Gsc\Services;

use Illuminate\Support\Facades\Http;

class SearchConsoleClient
{
    public function __construct(private readonly GscTokenService $tokens) {}

    public function properties(object $account): array
    {
        $response = $this->client($this->tokens->accessToken($account))
            ->get('https://www.googleapis.com/webmasters/v3/sites');

        if ($response->status() === 401) {
            $token = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($account->token_ciphertext), true);
            $response = $this->client($this->tokens->refresh($account, $token))
                ->get('https://www.googleapis.com/webmasters/v3/sites');
        }

        $response->throw();

        return $response->json()['siteEntry'] ?? [];
    }

    private function client(string $token): \Illuminate\Http\Client\PendingRequest
    {
        $http = Http::withToken($token);
        $proxy = config('gsc.http_proxy');

        return $proxy ? $http->withOptions(['proxy' => $proxy]) : $http;
    }
}
