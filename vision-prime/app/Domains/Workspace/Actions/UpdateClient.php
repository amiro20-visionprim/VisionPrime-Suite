<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Client;

class UpdateClient
{
    /** @param array{name:string, contact_name?:string|null, contact_email?:string|null, contact_phone?:string|null} $attributes */
    public function handle(Client $client, array $attributes): Client
    {
        $before = ['name' => $client->name, 'contact' => $client->contact];
        $contact = array_filter([
            'name' => $attributes['contact_name'] ?? null,
            'email' => $attributes['contact_email'] ?? null,
            'phone' => $attributes['contact_phone'] ?? null,
        ], fn (?string $value): bool => $value !== null && $value !== '');

        $client->update([
            'name' => $attributes['name'],
            'contact' => $contact === [] ? null : $contact,
        ]);

        $this->recordAuditLog->handle(
            action: 'client.updated',
            subject: $client,
            before: $before,
            after: ['name' => $client->name, 'contact' => $client->contact],
        );

        return $client->refresh();
    }

    public function __construct(private readonly RecordAuditLog $recordAuditLog) {}
}
