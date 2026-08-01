<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domains\Seo\Services\ClientGrowthSummary;
use App\Domains\Workspace\Contracts\CurrentClient;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ClientDashboardController extends Controller
{
    public function __invoke(CurrentClient $client, ClientGrowthSummary $summary): Response
    {
        return Inertia::render('Client/Dashboard', ['growthSummary' => $client->has() ? $summary->for($client->get()) : null]);
    }
}
