<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Client;

class ArchiveClient
{
    public function handle(Client $client): void
    {
        $before = ['name' => $client->name, 'status' => $client->status];
        $client->delete();

        $this->recordAuditLog->handle(
            action: 'client.archived',
            subject: $client,
            before: $before,
            after: ['deleted_at' => $client->deleted_at?->toIso8601String()],
        );
    }

    public function __construct(private readonly RecordAuditLog $recordAuditLog) {}
}
