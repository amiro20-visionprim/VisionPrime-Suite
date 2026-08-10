<?php

declare(strict_types=1);

namespace App\Domains\Gsc\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class GscMetricsClient
{
    public function __construct(private readonly GscTokenService $tokens) {}

    public function query(object $account, string $property, string $start, string $end, array $dimensions): array
    {
        $response = $this->client($this->tokens->accessToken($account))
            ->post('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($property).'/searchAnalytics/query', ['startDate' => $start, 'endDate' => $end, 'dimensions' => $dimensions, 'rowLimit' => 25000]);

        if ($response->status() === 401) {
            $token = json_decode(Crypt::decryptString($account->token_ciphertext), true);
            $response = $this->client($this->tokens->refresh($account, $token))
                ->post('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($property).'/searchAnalytics/query', ['startDate' => $start, 'endDate' => $end, 'dimensions' => $dimensions, 'rowLimit' => 25000]);
        }

        $response->throw();

        return $response->json();
    }

    private function client(string $token): \Illuminate\Http\Client\PendingRequest
    {
        $http = Http::withToken($token);
        $proxy = config('gsc.http_proxy');

        return $proxy ? $http->withOptions(['proxy' => $proxy]) : $http;
    }
}
