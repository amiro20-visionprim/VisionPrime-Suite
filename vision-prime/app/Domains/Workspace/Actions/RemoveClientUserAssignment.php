<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\ClientUserAssignment;

class RemoveClientUserAssignment
{
    public function handle(Client $client, ClientUserAssignment $assignment): void
    {
        abort_unless($assignment->client_id === $client->getKey(), 404);

        $this->recordAuditLog->handle(
            action: 'client.user_unassigned',
            subject: $client,
            before: ['user_id' => $assignment->user_id, 'portal_role' => $assignment->portal_role],
        );

        $assignment->delete();
    }

    public function __construct(private readonly RecordAuditLog $recordAuditLog) {}
}
