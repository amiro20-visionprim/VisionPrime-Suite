<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Client;
use Illuminate\Support\Str;

class CreateClient
{
    /** @param array{name:string, contact_name?:string|null, contact_email?:string|null, contact_phone?:string|null} $attributes */
    public function handle(array $attributes): Client
    {
        $organization = $this->currentOrganization->get();
        $contact = array_filter([
            'name' => $attributes['contact_name'] ?? null,
            'email' => $attributes['contact_email'] ?? null,
            'phone' => $attributes['contact_phone'] ?? null,
        ], fn (?string $value): bool => $value !== null && $value !== '');

        $client = Client::query()->create([
            'organization_id' => $organization->getKey(),
            'public_id' => (string) Str::ulid(),
            'name' => $attributes['name'],
            'status' => 'active',
            'contact' => $contact === [] ? null : $contact,
        ]);

        $this->recordAuditLog->handle(
            action: 'client.created',
            subject: $client,
            after: ['name' => $client->name, 'status' => $client->status],
        );

        return $client;
    }

    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly RecordAuditLog $recordAuditLog,
    ) {}
}
