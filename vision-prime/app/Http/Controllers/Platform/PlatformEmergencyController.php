<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domains\Platform\Actions\PlatformEmergencyStop;
use App\Domains\Platform\Services\PlatformNotifier;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformEmergencyController extends Controller
{
    public function __construct(
        private readonly PlatformEmergencyStop $stop,
        private readonly PlatformNotifier $notifier,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $orgId = isset($data['organization_id']) ? (int) $data['organization_id'] : null;

        $affected = $this->stop->handle($orgId, (string) $data['reason']);

        $scope = $orgId === null ? 'کل پلتفرم' : "سازمان #{$orgId}";
        $this->notifier->notify(
            "🚨 <b>توقف اضطراری</b> — {$scope} · {$affected} سایت متوقف شد. دلیل: ".htmlspecialchars((string) $data['reason']),
            'critical',
        );

        return back()->with('status', "توقف اضطراری اعمال شد — {$affected} سایت متوقف شد.");
    }
}
