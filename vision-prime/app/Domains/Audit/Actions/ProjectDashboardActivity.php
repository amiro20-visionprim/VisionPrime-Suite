<?php

declare(strict_types=1);

namespace App\Domains\Audit\Actions;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Support\Collection;

class ProjectDashboardActivity
{
    /** @return Collection<int, array<string, mixed>> */
    public function forOrganization(int $organizationId, int $limit = 8): Collection
    {
        return AuditLog::query()
            ->with('actor:id,name')
            ->where('organization_id', $organizationId)
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->getKey(),
                'action' => $log->action,
                'actorName' => $log->actor?->name ?? 'سامانه',
                'subjectType' => $log->subject_type,
                'subjectId' => $log->subject_id,
                'occurredAt' => $log->occurred_at?->toIso8601String(),
            ]);
    }
}
