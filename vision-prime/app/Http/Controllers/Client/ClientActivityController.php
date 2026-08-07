<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domains\Audit\Services\ActivityLabel;
use App\Domains\Workspace\Contracts\CurrentClient;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientActivityController extends Controller
{
    public function __invoke(CurrentClient $client): Response
    {
        if (! $client->has()) {
            return Inertia::render('Client/Activity', ['activities' => []]);
        }

        $clientModel = $client->get();

        $activities = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')
            ->where('audit_logs.organization_id', $clientModel->organization_id)
            ->latest('audit_logs.occurred_at')
            ->limit(30)
            ->get([
                'audit_logs.id',
                'audit_logs.action',
                'audit_logs.occurred_at',
                'users.name as actor_name',
            ])
            ->map(fn (object $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'label' => ActivityLabel::for($log->action),
                'actor_name' => $log->actor_name,
                'occurred_at' => $log->occurred_at,
            ])
            ->values();

        return Inertia::render('Client/Activity', ['activities' => $activities]);
    }
}
