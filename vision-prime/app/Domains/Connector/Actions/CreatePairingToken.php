<?php

declare(strict_types=1);

namespace App\Domains\Connector\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Site;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreatePairingToken
{
    /** @return array{token: string, expires_at: string} */
    public function handle(Site $site): array
    {
        $token = Str::random(64);
        $expiresAt = now()->addMinutes(15);

        \DB::table('pairing_tokens')->where('site_id', $site->id)->whereNull('consumed_at')->delete();
        \DB::table('pairing_tokens')->insert([
            'site_id' => $site->id,
            'token_hash' => Hash::make($token),
            'expires_at' => $expiresAt,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->handle(action: 'connector.pairing_token_created', subject: $site, after: ['expires_at' => $expiresAt->toIso8601String()]);

        return ['token' => $token, 'expires_at' => $expiresAt->toIso8601String()];
    }

    public function __construct(private readonly RecordAuditLog $audit) {}
}
