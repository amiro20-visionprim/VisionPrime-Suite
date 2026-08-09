<?php

declare(strict_types=1);

namespace App\Domains\Gsc\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Keeps GSC access tokens usable: access tokens expire after ~1h and the
 * stored refresh_token is the only way to mint a new one. Without this,
 * every connected account silently breaks an hour after connecting.
 */
class GscTokenService
{
    /**
     * Return a usable access token for the account, refreshing first when the
     * stored one is expired.
     */
    public function accessToken(object $account): string
    {
        $token = json_decode(Crypt::decryptString($account->token_ciphertext), true);
        $expiresAt = $account->token_expires_at ? now()->parse((string) $account->token_expires_at) : null;

        if (($expiresAt === null || $expiresAt->subMinute()->isPast()) && isset($token['refresh_token'])) {
            return $this->refresh($account, $token);
        }

        return $token['access_token'];
    }

    /**
     * Exchange the refresh_token for a fresh access token and persist it.
     */
    public function refresh(object $account, array $token): string
    {
        $response = Http::asForm()
            ->timeout(30)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('gsc.client_id'),
                'client_secret' => config('gsc.client_secret'),
                'refresh_token' => $token['refresh_token'],
                'grant_type' => 'refresh_token',
            ])
            ->throw()
            ->json();

        $fresh = array_merge($token, $response);

        \DB::table('gsc_accounts')->where('id', $account->id)->update([
            'token_ciphertext' => Crypt::encryptString(json_encode($fresh)),
            'token_expires_at' => now()->addSeconds((int) ($response['expires_in'] ?? 3600)),
            'updated_at' => now(),
        ]);

        return $response['access_token'];
    }
}
