<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client;

use App\Domains\Audit\Actions\RecordAuditLog;
use App\Domains\Workspace\Contracts\CurrentClient;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Services\ClientAccessScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CurrentClientController extends Controller
{
    public function update(
        Request $request,
        Client $client,
        ClientAccessScope $clientAccessScope,
        CurrentClient $currentClient,
        RecordAuditLog $recordAuditLog,
    ): RedirectResponse {
        abort_unless($clientAccessScope->canAccess($request->user(), $client), 403);

        $request->session()->put('current_client_id', $client->getKey());
        $currentClient->set($client);
        $recordAuditLog->handle(
            action: 'client.selected',
            subject: $client,
            after: ['name' => $client->name],
        );

        return back();
    }
}
