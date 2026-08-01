<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Connector\Actions\DisconnectSite;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SiteDisconnectController extends Controller
{
    public function __invoke(Site $site, DisconnectSite $disconnect): RedirectResponse
    {
        Gate::authorize('update', $site);
        $disconnect->handle($site);

        return back()->with('status', 'اتصال وردپرس با موفقیت قطع شد.');
    }
}
