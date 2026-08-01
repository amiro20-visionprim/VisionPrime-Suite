<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Organization\Models\Membership;
use App\Domains\Workspace\Contracts\CurrentClient;
use App\Domains\Workspace\Services\ClientAccessScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientPortalAccess
{
    /** @var array<int, string> */
    private const ALLOWED_ROLE_KEYS = ['agency-admin', 'client-viewer', 'client-approver'];

    public function __construct(
        private readonly ClientAccessScope $clientAccessScope,
        private readonly CurrentClient $currentClient,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = app(CurrentOrganization::class)->get();

        $roleKey = Membership::query()
            ->where('user_id', $user?->getKey())
            ->where('organization_id', $organization->getKey())
            ->where('status', 'active')
            ->with('role:id,key')
            ->first()?->role?->key;

        abort_unless(in_array($roleKey, self::ALLOWED_ROLE_KEYS, true), 403);

        $visibleClients = $this->clientAccessScope->visibleQuery($user, $organization);
        $selectedClientId = $request->session()->get('current_client_id');
        $client = $selectedClientId === null
            ? $visibleClients->orderBy('name')->first()
            : (clone $visibleClients)->whereKey($selectedClientId)->first();

        if ($client === null && $selectedClientId !== null) {
            $request->session()->forget('current_client_id');
            $client = $visibleClients->orderBy('name')->first();
        }

        if ($client === null && $roleKey !== 'agency-admin') {
            abort(403);
        }

        if ($client !== null) {
            $request->session()->put('current_client_id', $client->getKey());
            $this->currentClient->set($client);
        }

        return $next($request);
    }
}
