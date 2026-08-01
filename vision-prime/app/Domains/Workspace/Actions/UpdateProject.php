<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Project;

class UpdateProject
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(Project $project, string $name, ?string $objective): Project
    {
        $before = ['name' => $project->name, 'objective' => $project->objective];
        $project->update(['name' => $name, 'objective' => $objective]);
        $this->audit->handle(action: 'project.updated', subject: $project, before: $before, after: ['name' => $project->name, 'objective' => $project->objective]);

        return $project->refresh();
    }
}
