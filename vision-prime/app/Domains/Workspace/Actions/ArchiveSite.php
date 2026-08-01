<?php

declare(strict_types=1);

namespace App\Domains\Workspace\Actions;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Models\Site;

class ArchiveSite
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function handle(Site $site): void
    {
        $before = ['name' => $site->name, 'canonical_url' => $site->canonical_url];
        $site->delete();
        $this->audit->handle(action: 'site.archived', subject: $site, before: $before, after: ['deleted_at' => $site->deleted_at?->toIso8601String()]);
    }
}
