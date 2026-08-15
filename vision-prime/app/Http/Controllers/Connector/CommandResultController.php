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
            'integrity_hash' => ['nullable', 'string', 'max:64', 'regex:/^[a-f0-9]{64}$/'],
            'tampered' => ['nullable', 'boolean'],
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

        $tampered = filter_var($data['tampered'] ?? false, FILTER_VALIDATE_BOOL);
        if ($tampered) {
            \DB::table('site_connections')->where('id', $connection->id)->update(['status' => 'degraded', 'updated_at' => now()]);
            \DB::table('connector_events')->insert([
                'site_id' => $connection->site_id,
                'type' => 'security.tamper_detected',
                'payload_redacted' => json_encode(['integrity_hash' => $data['integrity_hash'] ?? null]),
                'occurred_at' => now(),
            ]);
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
            \DB::table('commands')->where('id', $command->id)->update([
                'status' => $status,
                'published_at' => $status === 'executed' && $command->published_at === null ? now() : $command->published_at,
                'updated_at' => now(),
            ]);

            // ذخیرهٔ snapshot پیش از تغییر (برای بازگشت خودکار D-013) وقتی نتیجه، مقدار قبلی را دارد.
            if ($status === 'executed' && isset($data['result']['previous'])) {
                $postId = $data['result']['post_id'] ?? null;
                $target = $postId !== null ? 'post:'.(int) $postId : $this->targetUrl($command);
                \DB::table('rollback_snapshots')->insert([
                    'command_id' => $command->id,
                    'target_ref' => $target,
                    'snapshot_ciphertext' => \Crypt::encryptString(json_encode([
                        'type' => $command->type,
                        'previous' => $data['result']['previous'],
                        'post_id' => $postId,
                    ], JSON_UNESCAPED_UNICODE)),
                    'expires_at' => now()->addDays(30),
                    'status' => 'available',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            \DB::table('connector_events')->insert([
                'site_id' => $connection->site_id,
                'type' => 'command.result_received',
                'payload_redacted' => json_encode(['command_id' => $command->id, 'status' => $status]),
                'occurred_at' => now(),
            ]);
        });

        return response()->json(['status' => 'ack']);
    }

    private function targetUrl(object $command): string
    {
        $payload = json_decode((string) $command->payload, true);

        return is_array($payload) && isset($payload['url']) ? (string) $payload['url'] : 'post:unknown';
    }
}
