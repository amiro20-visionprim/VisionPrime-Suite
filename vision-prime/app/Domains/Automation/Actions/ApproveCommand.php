<?php

declare(strict_types=1);

namespace App\Domains\Automation\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Models\User;

class ApproveCommand
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(int $commandId, User $reviewer, string $decision, ?string $note = null): void
    {
        $command = \DB::table('commands')->where('id', $commandId)->firstOrFail();
        abort_unless($command->status === 'pending_approval', 422);
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        \DB::transaction(function () use ($command, $reviewer, $decision, $note) {
            \DB::table('command_approvals')->insert(['command_id' => $command->id, 'reviewer_id' => $reviewer->id, 'decision' => $decision, 'note' => $note, 'policy_snapshot' => json_encode(['policy_version' => $command->policy_version]), 'created_at' => now(), 'updated_at' => now()]);
            \DB::table('commands')->where('id', $command->id)->update(['status' => $decision === 'approved' ? 'approved' : 'cancelled', 'updated_at' => now()]);
        });
        $this->audit->handle(action: 'command.approval_decided', after: ['command_id' => $commandId, 'decision' => $decision]);
    }
}
