<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use Illuminate\Support\Str;

class CreateProject
{
    public function __construct(private readonly CurrentOrganization $currentOrganization, private readonly RecordAuditLog $audit) {}

    public function handle(Client $client, string $name, ?string $objective): Project
    {
        $project = Project::query()->create(['organization_id' => $this->currentOrganization->get()->getKey(), 'client_id' => $client->getKey(), 'public_id' => (string) Str::ulid(), 'name' => $name, 'objective' => $objective, 'status' => 'active']);
        $this->audit->handle(action: 'project.created', subject: $project, after: ['name' => $project->name, 'client_id' => $client->getKey()]);

        return $project;
    }
}
