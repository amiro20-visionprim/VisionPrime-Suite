<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connector;

use App\Domains\Connector\Services\VerifyConnectorSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthCheckController
{
    public function __invoke(Request $request, VerifyConnectorSignature $verify): JsonResponse
    {
        $connection = \DB::table('site_connections')->where('site_id', $request->integer('site_id'))->where('status', 'connected')->firstOrFail();
        $verify->handle($connection, $request->method(), $request->path(), $request->getContent(), (string) $request->header('X-VP-Timestamp'), (string) $request->header('X-VP-Nonce'), (string) $request->header('X-VP-Signature'));
        $health = $request->validate([
            'plugin_version' => ['required', 'string', 'max:50'],
            'wordpress_version' => ['required', 'string', 'max:50'],
            'php_version' => ['required', 'string', 'max:50'],
            'rest_api' => ['required', 'boolean'],
            'integrity_hash' => ['nullable', 'string', 'max:64', 'regex:/^[a-f0-9]{64}$/'],
            'tampered' => ['nullable', 'boolean'],
        ]);

        $tampered = filter_var($health['tampered'] ?? false, FILTER_VALIDATE_BOOL);
        \DB::table('site_connections')->where('id', $connection->id)->update([
            'plugin_version' => $health['plugin_version'],
            'health' => json_encode($health, JSON_UNESCAPED_UNICODE),
            'last_seen_at' => now(),
            'updated_at' => now(),
            ...($tampered ? ['status' => 'degraded'] : []),
        ]);
        \DB::table('connector_events')->insert(['site_id' => $connection->site_id, 'type' => 'health.checked', 'payload_redacted' => json_encode($health, JSON_UNESCAPED_UNICODE), 'occurred_at' => now()]);
        if ($tampered) {
            \DB::table('connector_events')->insert([
                'site_id' => $connection->site_id,
                'type' => 'security.tamper_detected',
                'payload_redacted' => json_encode(['integrity_hash' => $health['integrity_hash'] ?? null, 'plugin_version' => $health['plugin_version']]),
                'occurred_at' => now(),
            ]);
        }

        return response()->json(['status' => 'ok', 'server_time' => now()->toIso8601String()]);
    }
}
