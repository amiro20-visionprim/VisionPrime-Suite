<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class DispatchCommand
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(int $commandId): array
    {
        $command = \DB::table('commands')->where('id', $commandId)->firstOrFail();
        abort_unless($command->status === 'approved', 422);
        abort_unless(now()->lt($command->expires_at), 422);
        $connection = \DB::table('site_connections')->where('site_id', $command->site_id)->where('status', 'connected')->firstOrFail();
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $body = json_encode(['command_id' => $command->id, 'type' => $command->type, 'payload' => json_decode($command->payload, true), 'idempotency_key' => $command->idempotency_key]);
        $signature = hash_hmac('sha256', "POST\n/vision-prime/v1/commands\n{$timestamp}\n{$nonce}\n".hash('sha256', $body), Crypt::decryptString($connection->secret_ciphertext));
        \DB::table('commands')->where('id', $command->id)->update(['status' => 'dispatched', 'updated_at' => now()]);
        \DB::table('command_execution_logs')->insert(['command_id' => $command->id, 'attempt' => 1, 'status' => 'dispatched', 'request_redacted' => json_encode(['timestamp' => $timestamp, 'nonce' => $nonce]), 'created_at' => now(), 'updated_at' => now()]);
        $this->audit->handle(action: 'command.dispatched', after: ['command_id' => $command->id]);

        return ['url' => rtrim($connection->platform_url, '/').'/wp-json/vision-prime/v1/commands', 'body' => $body, 'timestamp' => $timestamp, 'nonce' => $nonce, 'signature' => $signature];
    }
}
