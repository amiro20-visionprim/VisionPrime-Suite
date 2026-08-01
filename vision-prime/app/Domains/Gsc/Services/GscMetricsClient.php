<?php

declare(strict_types=1);

namespace App\Domains\Gsc\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class GscMetricsClient
{
    public function query(object $account, string $property, string $start, string $end, array $dimensions): array
    {
        $token = json_decode(Crypt::decryptString($account->token_ciphertext), true);

        return Http::withToken($token['access_token'])->post('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($property).'/searchAnalytics/query', ['startDate' => $start, 'endDate' => $end, 'dimensions' => $dimensions, 'rowLimit' => 25000])->throw()->json();
    }
}
