<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use Illuminate\Support\Facades\Crypt;

class CreateRollbackSnapshot
{
    public function handle(int $commandId, string $targetRef, array $snapshot): int
    {
        return \DB::table('rollback_snapshots')->insertGetId(['command_id' => $commandId, 'target_ref' => $targetRef, 'snapshot_ciphertext' => Crypt::encryptString(json_encode($snapshot)), 'expires_at' => now()->addDays(30), 'status' => 'available', 'created_at' => now(), 'updated_at' => now()]);
    }
}
