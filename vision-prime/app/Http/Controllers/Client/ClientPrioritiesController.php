<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domains\Workspace\Contracts\CurrentClient;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientPrioritiesController extends Controller
{
    public function __invoke(CurrentClient $client): Response
    {
        if (! $client->has()) {
            return Inertia::render('Client/Priorities', ['opportunities' => [], 'recommendations' => []]);
        }

        $clientModel = $client->get();

        $opportunities = DB::table('opportunities')
            ->join('sites', 'sites.id', '=', 'opportunities.site_id')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->where('projects.client_id', $clientModel->getKey())
            ->where('opportunities.status', 'open')
            ->orderByDesc('opportunities.score')
            ->get([
                'opportunities.id',
                'opportunities.type',
                'opportunities.score',
                'opportunities.confidence',
                'opportunities.explanation',
                'sites.name as site_name',
            ]);

        $recommendations = DB::table('recommendations')
            ->join('sites', 'sites.id', '=', 'recommendations.site_id')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->leftJoin('users', 'users.id', '=', 'recommendations.owner_id')
            ->where('projects.client_id', $clientModel->getKey())
            ->whereIn('recommendations.status', ['draft', 'active'])
            ->orderByDesc('recommendations.created_at')
            ->get([
                'recommendations.id',
                'recommendations.title',
                'recommendations.body',
                'recommendations.priority',
                'recommendations.status',
                'recommendations.due_at',
                'recommendations.created_at',
                'sites.name as site_name',
                'users.name as owner_name',
            ]);

        return Inertia::render('Client/Priorities', [
            'opportunities' => $opportunities,
            'recommendations' => $recommendations,
        ]);
    }
}
