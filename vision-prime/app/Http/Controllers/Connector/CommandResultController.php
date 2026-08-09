<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connector;

use App\Domains\Connector\Services\VerifyConnectorSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandResultController
{
    public function __invoke(Request $request, VerifyConnectorSignature $verify): JsonResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'integer'],
            'idempotency_key' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'in:executed,failed,rolled_back'],
            'result' => ['nullable', 'array'],
            'error' => ['nullable', 'string', 'max:2000'],
        ]);

        $connection = \DB::table('site_connections')
            ->where('site_id', $data['site_id'])
            ->where('status', 'connected')
            ->firstOrFail();
        $verify->handle($connection, $request->method(), $request->path(), $request->getContent(), (string) $request->header('X-VP-Timestamp'), (string) $request->header('X-VP-Nonce'), (string) $request->header('X-VP-Signature'));

        $command = \DB::table('commands')->where('idempotency_key', $data['idempotency_key'])->firstOrFail();
        if ($command->site_id !== $connection->site_id) {
            abort(403, 'این دستور متعلق به این سایت نیست.');
        }

        $status = match ($data['status']) {
            'executed' => 'executed',
            'rolled_back' => 'rolled_back',
            default => 'failed',
        };

        \DB::transaction(function () use ($command, $data, $status, $connection): void {
            \DB::table('command_execution_logs')
                ->where('command_id', $command->id)
                ->where('status', 'dispatched')
                ->update([
                    'status' => $status,
                    'response_redacted' => json_encode([
                        'callback' => true,
                        'result' => $data['result'] ?? null,
                        'error' => $data['error'] ?? null,
                    ], JSON_UNESCAPED_UNICODE),
                    'executed_at' => now(),
                    'updated_at' => now(),
                ]);
            \DB::table('commands')->where('id', $command->id)->update(['status' => $status, 'updated_at' => now()]);
            \DB::table('connector_events')->insert([
                'site_id' => $connection->site_id,
                'type' => 'command.result_received',
                'payload_redacted' => json_encode(['command_id' => $command->id, 'status' => $status]),
                'occurred_at' => now(),
            ]);
        });

        return response()->json(['status' => 'ack']);
    }
}
