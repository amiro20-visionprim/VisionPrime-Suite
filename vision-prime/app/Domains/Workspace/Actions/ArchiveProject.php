<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Project;

class ArchiveProject
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(Project $project): void
    {
        $before = ['name' => $project->name, 'status' => $project->status];
        $project->delete();
        $this->audit->handle(action: 'project.archived', subject: $project, before: $before, after: ['deleted_at' => $project->deleted_at?->toIso8601String()]);
    }
}
