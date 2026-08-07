<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domains\Workspace\Contracts\CurrentClient;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClientDecisionsController extends Controller
{
    public function __invoke(CurrentClient $client): Response
    {
        if (! $client->has()) {
            return Inertia::render('Client/Decisions', ['commands' => [], 'reviews' => []]);
        }

        $clientModel = $client->get();

        $commands = DB::table('commands')
            ->join('sites', 'sites.id', '=', 'commands.site_id')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->where('projects.client_id', $clientModel->getKey())
            ->where('commands.status', 'pending_approval')
            ->latest('commands.created_at')
            ->get([
                'commands.id',
                'commands.type',
                'commands.risk_tier',
                'commands.expires_at',
                'commands.created_at',
                'sites.name as site_name',
            ]);

        $reviews = DB::table('review_items')
            ->join('sites', 'sites.id', '=', 'review_items.site_id')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->where('projects.client_id', $clientModel->getKey())
            ->where('review_items.status', 'pending_review')
            ->latest('review_items.created_at')
            ->get([
                'review_items.id',
                'review_items.subject_type',
                'review_items.subject_id',
                'review_items.due_at',
                'review_items.created_at',
                'sites.name as site_name',
            ]);

        return Inertia::render('Client/Decisions', [
            'commands' => $commands,
            'reviews' => $reviews,
        ]);
    }
}
