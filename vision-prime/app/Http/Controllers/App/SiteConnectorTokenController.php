<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Connector\Actions\CreatePairingToken;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SiteConnectorTokenController extends Controller
{
    public function store(Site $site, CreatePairingToken $pairing): RedirectResponse
    {
        Gate::authorize('update', $site);
        $result = $pairing->handle($site);

        return back()->with('pairingToken', $result['token'])->with('pairingTokenExpiresAt', $result['expires_at']);
    }
}
