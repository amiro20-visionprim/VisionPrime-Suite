<?php

declare(strict_types=1);

namespace App\Domains\Connector\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConsumePairingToken
{
    /** @return array{secret: string, connection_id: int} */
    public function handle(Site $site, string $token, string $platformUrl, string $pluginVersion): array
    {
        $record = \DB::table('pairing_tokens')->where('site_id', $site->id)->whereNull('consumed_at')->where('expires_at', '>', now())->latest('id')->first();
        if ($record === null || ! Hash::check($token, $record->token_hash)) {
            throw ValidationException::withMessages(['token' => 'کد اتصال معتبر نیست یا منقضی شده است.']);
        }
        $secret = Str::random(80);
        $connectionId = \DB::table('site_connections')->updateOrInsert(['site_id' => $site->id], ['status' => 'connected', 'platform_url' => $platformUrl, 'plugin_version' => $pluginVersion, 'secret_ciphertext' => Crypt::encryptString($secret), 'last_seen_at' => now(), 'updated_at' => now(), 'created_at' => now()]);
        \DB::table('pairing_tokens')->where('id', $record->id)->update(['consumed_at' => now(), 'updated_at' => now()]);
        $connection = \DB::table('site_connections')->where('site_id', $site->id)->first();
        $this->audit->handle(action: 'connector.paired', subject: $site, after: ['plugin_version' => $pluginVersion]);

        return ['secret' => $secret, 'connection_id' => $connection->id];
    }

    public function __construct(private readonly RecordAuditLog $audit) {}
}
