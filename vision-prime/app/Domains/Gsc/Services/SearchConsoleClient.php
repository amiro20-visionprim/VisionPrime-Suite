<?php

declare(strict_types=1);

namespace App\Domains\Gsc\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;

class SearchConsoleClient
{
    public function __construct(
        private readonly GscTokenService $tokens,
        private readonly GscHttp $http,
    ) {}

    public function properties(object $account): array
    {
        $response = $this->client($this->tokens->accessToken($account))
            ->get('https://www.googleapis.com/webmasters/v3/sites');

        if ($response->status() === 401) {
            $token = json_decode(Crypt::decryptString($account->token_ciphertext), true);
            $response = $this->client($this->tokens->refresh($account, $token))
                ->get('https://www.googleapis.com/webmasters/v3/sites');
        }

        $response->throw();

        return $response->json()['siteEntry'] ?? [];
    }

    private function client(string $token): PendingRequest
    {
        return $this->http->request(['Authorization' => 'Bearer '.$token]);
    }
}
