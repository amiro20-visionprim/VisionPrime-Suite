<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connector;

use App\Domains\Connector\Actions\ConsumePairingToken;
use App\Domains\Workspace\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PairSiteController
{
    public function __invoke(Request $request, ConsumePairingToken $consume): JsonResponse
    {
        $data = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'pairing_token' => ['required', 'string', 'size:64'],
            'platform_url' => ['required', 'url', 'max:2048'],
            'plugin_version' => ['required', 'string', 'max:50'],
        ]);
        $site = Site::query()->findOrFail($data['site_id']);
        $result = $consume->handle($site, $data['pairing_token'], $data['platform_url'], $data['plugin_version']);

        return response()->json(['connection_id' => $result['connection_id'], 'secret' => $result['secret']]);
    }
}
