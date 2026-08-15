<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Reporting\Actions\CreateImpactEvent;
use App\Domains\Workspace\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * بازگشت خودکار (D-013 فاز ۳).
 *
 * snapshot رمزنگاری‌شدهٔ پیش از تغییر را باز می‌کند و با امضای HMAC (همان قرارداد
 * DispatchCommand) به endpoint رولبک پلاگین وردپرس می‌فرستد؛ در موفقیت، وضعیت
 * command به rolled_back می‌رود، snapshot مصرف می‌شود و رویداد اثر با outcome ثبت می‌گردد.
 */
class RollbackCommand
{
    public function __construct(
        private readonly RecordAuditLog $audit,
        private readonly CreateImpactEvent $impact,
    ) {}

    /** @return array{rolled_back: bool, reason?: string} */
    public function handle(int $commandId): array
    {
        $command = \DB::table('commands')->where('id', $commandId)->first();

        if ($command === null || $command->status !== 'executed') {
            return ['rolled_back' => false, 'reason' => 'command_not_executed'];
        }

        $snapshot = \DB::table('rollback_snapshots')
            ->where('command_id', $commandId)
            ->where('status', 'available')
            ->orderByDesc('id')
            ->first();

        if ($snapshot === null) {
            return ['rolled_back' => false, 'reason' => 'no_rollback_snapshot'];
        }

        $decoded = json_decode(Crypt::decryptString($snapshot->snapshot_ciphertext), true);
        if (! is_array($decoded) || empty($decoded['previous']) || ! is_array($decoded['previous'])) {
            return ['rolled_back' => false, 'reason' => 'invalid_snapshot'];
        }

        $connection = \DB::table('site_connections')->where('site_id', $command->site_id)->where('status', 'connected')->first();
        if ($connection === null) {
            return ['rolled_back' => false, 'reason' => 'site_not_connected'];
        }

        $body = json_encode([
            'command_id' => $command->id,
            'type' => $command->type,
            'previous' => $decoded['previous'],
            'post_id' => $decoded['post_id'] ?? null,
            'idempotency_key' => 'rollback-'.$command->idempotency_key,
        ], JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $path = '/vision-prime/v1/rollback';
        $signature = hash_hmac('sha256', "POST\n{$path}\n{$timestamp}\n{$nonce}\n".hash('sha256', $body), Crypt::decryptString($connection->secret_ciphertext));

        try {
            $response = Http::timeout(60)
                ->acceptJson()
                ->withHeaders([
                    'X-VP-Timestamp' => $timestamp,
                    'X-VP-Nonce' => $nonce,
                    'X-VP-Signature' => $signature,
                    'X-VP-Idempotency-Key' => $body['idempotency_key'] ?? 'rollback-'.$command->idempotency_key,
                ])
                ->withBody($body, 'application/json')
                ->post(rtrim($connection->platform_url, '/').'/wp-json'.$path);
        } catch (\Throwable $e) {
            $this->audit->handle(action: 'command.rollback_failed', after: ['command_id' => $commandId, 'error' => $e->getMessage()]);

            return ['rolled_back' => false, 'reason' => 'request_failed'];
        }

        if (! $response->successful()) {
            $this->audit->handle(action: 'command.rollback_failed', after: ['command_id' => $commandId, 'http_status' => $response->status()]);

            return ['rolled_back' => false, 'reason' => 'http_'.$response->status()];
        }

        // پلاگین نتیجهٔ واقعی بازگردانی را همگام برمی‌گرداند؛ بدون تأیید آن،
        // علامت‌گذاری rolled_back گمراه‌کننده است (بازگشت می‌تواند روی وردپرس شکست خورده باشد).
        $payload = $response->json();
        if (! is_array($payload) || ($payload['restored'] ?? false) !== true) {
            $this->audit->handle(action: 'command.rollback_failed', after: ['command_id' => $commandId, 'plugin_reason' => $payload['result']['error'] ?? 'restore_not_confirmed']);

            return ['rolled_back' => false, 'reason' => 'restore_not_confirmed'];
        }

        \DB::transaction(function () use ($command, $snapshot): void {
            \DB::table('commands')->where('id', $command->id)->update(['status' => 'rolled_back', 'updated_at' => now()]);
            \DB::table('rollback_snapshots')->where('id', $snapshot->id)->update(['status' => 'used', 'updated_at' => now()]);
        });

        $this->audit->handle(action: 'command.rolled_back', after: ['command_id' => $command->id, 'snapshot_id' => $snapshot->id]);

        $site = Site::query()->find($command->site_id);
        if ($site !== null) {
            $this->impact->handle(
                $site,
                'command',
                $command->id,
                ['command_type' => $command->type, 'rolled_back' => true],
                'بازگشت خودکار تغییر «'.$command->type.'» به دلیل افت زیر baseline.',
            );
        }

        return ['rolled_back' => true];
    }
}
