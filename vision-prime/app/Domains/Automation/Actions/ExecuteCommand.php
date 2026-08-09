<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use Illuminate\Support\Facades\Http;

class ExecuteCommand
{
    public function __construct(
        private readonly DispatchCommand $dispatch,
        private readonly RecordAuditLog $audit,
    ) {}

    public function handle(int $commandId): array
    {
        $signed = $this->dispatch->handle($commandId);

        try {
            $response = Http::timeout(60)
                ->acceptJson()
                ->withHeaders([
                    'X-VP-Timestamp' => $signed['timestamp'],
                    'X-VP-Nonce' => $signed['nonce'],
                    'X-VP-Signature' => $signed['signature'],
                    'X-VP-Idempotency-Key' => json_decode($signed['body'], true)['idempotency_key'] ?? '',
                ])
                ->post($signed['url'], json_decode($signed['body'], true)['payload'] ?? []);

            $responseRedacted = [
                'http_status' => $response->status(),
                'body' => $response->json() ?? mb_substr($response->body(), 0, 2000),
            ];

            if (! $response->successful()) {
                \DB::table('command_execution_logs')
                    ->where('command_id', $commandId)
                    ->where('status', 'dispatched')
                    ->update([
                        'status' => 'failed',
                        'response_redacted' => json_encode($responseRedacted, JSON_UNESCAPED_UNICODE),
                        'executed_at' => now(),
                        'updated_at' => now(),
                    ]);
                \DB::table('commands')->where('id', $commandId)->update(['status' => 'failed', 'updated_at' => now()]);

                throw new \RuntimeException('وردپرس با خطای '.$response->status().' پاسخ داد: '.mb_substr($response->body(), 0, 200));
            }

            \DB::table('command_execution_logs')
                ->where('command_id', $commandId)
                ->where('status', 'dispatched')
                ->update([
                    'status' => 'executed',
                    'response_redacted' => json_encode($responseRedacted, JSON_UNESCAPED_UNICODE),
                    'executed_at' => now(),
                    'updated_at' => now(),
                ]);
            \DB::table('commands')->where('id', $commandId)->update([
                'status' => 'executed',
                'updated_at' => now(),
            ]);
            $this->audit->handle(action: 'command.executed', after: ['command_id' => $commandId, 'http_status' => $response->status()]);

            return $responseRedacted;
        } catch (\Throwable $e) {
            \DB::table('command_execution_logs')
                ->where('command_id', $commandId)
                ->where('status', 'dispatched')
                ->update([
                    'status' => 'failed',
                    'response_redacted' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE),
                    'executed_at' => now(),
                    'updated_at' => now(),
                ]);
            \DB::table('commands')->where('id', $commandId)->update(['status' => 'failed', 'updated_at' => now()]);

            throw $e;
        }
    }
}
