<?php

declare(strict_types=1);

namespace App\Domains\Connector\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class VerifyConnectorSignature
{
    public function handle(object $connection, string $method, string $path, string $body, string $timestamp, string $nonce, string $signature): void
    {
        if (abs(now()->timestamp - (int) $timestamp) > 300) {
            throw ValidationException::withMessages(['timestamp' => 'درخواست منقضی شده است.']);
        }
        $exists = \DB::table('connector_nonces')->where('site_connection_id', $connection->id)->where('nonce', $nonce)->where('expires_at', '>', now())->exists();
        if ($exists) {
            throw ValidationException::withMessages(['nonce' => 'درخواست تکراری است.']);
        }
        $payload = strtoupper($method)."\n".$path."\n".$timestamp."\n".$nonce."\n".hash('sha256', $body);
        $expected = hash_hmac('sha256', $payload, Crypt::decryptString($connection->secret_ciphertext));
        if (! hash_equals($expected, $signature)) {
            throw ValidationException::withMessages(['signature' => 'امضای درخواست معتبر نیست.']);
        }
        \DB::table('connector_nonces')->insert(['site_connection_id' => $connection->id, 'nonce' => $nonce, 'expires_at' => now()->addMinutes(10), 'used_at' => now()]);
    }
}
