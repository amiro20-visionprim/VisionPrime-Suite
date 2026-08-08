<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Services\ActivityLabel;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Services\OrganizationPermission;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogSettingsController extends Controller
{
    public function __construct(
        private readonly OrganizationPermission $organizationPermission,
    ) {}

    public function index(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->get();
        $user = $request->user();

        if ($user === null || ! $this->organizationPermission->allows($user, $organization, 'audit.view.organization')) {
            abort(403, 'شما دسترسی مشاهدهٔ گزارش ممیزی را ندارید.');
        }

        $query = AuditLog::query()
            ->where('organization_id', $organization->getKey())
            ->with('actor:id,name,email');

        $selectedAction = $request->string('action')->toString();
        if ($selectedAction !== '') {
            $query->where('action', $selectedAction);
        }

        $logs = $query
            ->orderByDesc('occurred_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => $this->logItem($log));

        return Inertia::render('App/Settings/AuditLog', [
            'logs' => $logs,
            'actionOptions' => AuditLog::query()
                ->where('organization_id', $organization->getKey())
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->map(fn (string $action): array => [
                    'value' => $action,
                    'label' => ActivityLabel::for($action),
                ])
                ->values(),
            'selectedAction' => $selectedAction,
        ]);
    }

    /** @return array<string, mixed> */
    private function logItem(AuditLog $log): array
    {
        return [
            'id' => $log->getKey(),
            'action' => $log->action,
            'actionLabel' => ActivityLabel::for($log->action),
            'actorName' => $log->actor?->name,
            'actorEmail' => $log->actor?->email,
            'subjectType' => $log->subject_type,
            'subjectId' => $log->subject_id,
            'source' => $log->source,
            'occurredAt' => $log->occurred_at?->toIso8601String(),
        ];
    }
}
