<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Ai\Actions\GenerateMetaDraft;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiDraftController extends Controller
{
    public function store(Request $request, CurrentOrganization $org, GenerateMetaDraft $generate): RedirectResponse
    {
        $data = $request->validate([
            'url_profile_id' => ['required', 'integer'],
            'kind' => ['required', 'in:'.implode(',', GenerateMetaDraft::KINDS)],
        ]);

        $siteIds = Site::query()->where('organization_id', $org->id())->pluck('id');
        $profile = \DB::table('url_profiles')
            ->whereIn('site_id', $siteIds)
            ->where('id', (int) $data['url_profile_id'])
            ->first();

        if ($profile === null) {
            abort(404);
        }

        $site = Site::query()->findOrFail($profile->site_id);

        try {
            $generationId = $generate->handle($site, $data['kind'], (int) $profile->id);
        } catch (\Throwable $e) {
            return back()->with('error', 'تولید پیشنویس ناموفق بود: '.$e->getMessage());
        }

        return back()->with('status', 'پیشنویس با هوش مصنوعی تولید شد و برای بازبینی ثبت گردید.');
    }
}
