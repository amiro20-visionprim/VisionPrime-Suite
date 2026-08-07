<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domains\Ai\Actions\DecideReviewItem;
use App\Domains\Automation\Actions\ApproveCommand;
use App\Domains\Workspace\Contracts\CurrentClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientDecisionController extends Controller
{
    public function command(
        int $command,
        Request $request,
        CurrentClient $client,
        ApproveCommand $approveCommand,
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approved,rejected'],
        ]);

        $scoped = DB::table('commands')
            ->whereIn('site_id', $this->clientSiteIds($client))
            ->where('id', $command)
            ->where('status', 'pending_approval')
            ->exists();

        abort_unless($scoped, 404);

        $approveCommand->handle($command, $request->user(), $data['decision']);

        return back();
    }

    public function review(
        int $review,
        Request $request,
        CurrentClient $client,
        DecideReviewItem $decideReviewItem,
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approved,rejected,changes_requested'],
        ]);

        $scoped = DB::table('review_items')
            ->whereIn('site_id', $this->clientSiteIds($client))
            ->where('id', $review)
            ->where('status', 'pending_review')
            ->exists();

        abort_unless($scoped, 404);

        $decideReviewItem->handle($review, $request->user(), $data['decision']);

        return back();
    }

    /** @return array<int, int> */
    private function clientSiteIds(CurrentClient $client): array
    {
        if (! $client->has()) {
            return [];
        }

        return DB::table('sites')
            ->join('projects', 'projects.id', '=', 'sites.project_id')
            ->where('projects.client_id', $client->get()->getKey())
            ->pluck('sites.id')
            ->all();
    }
}
