<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domains\Workspace\Contracts\CurrentClient;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ClientReportController extends Controller
{
    public function index(CurrentClient $client): Response
    {
        if (! $client->has()) {
            return Inertia::render('Client/Reports', ['reports' => []]);
        }$siteIds = \DB::table('sites')->join('projects', 'projects.id', '=', 'sites.project_id')->where('projects.client_id', $client->id())->pluck('sites.id');
        $reports = \DB::table('reports')->whereIn('site_id', $siteIds)->where('status', 'published')->latest('published_at')->get(['id', 'type', 'period_start', 'period_end', 'content', 'published_at']);

        return Inertia::render('Client/Reports', ['reports' => $reports]);
    }
}
