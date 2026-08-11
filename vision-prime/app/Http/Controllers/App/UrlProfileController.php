<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Content\Models\UrlProfile;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UrlProfileController extends Controller
{
    public function index(CurrentOrganization $context): Response
    {
        $siteIds = Site::query()->where('organization_id', $context->id())->pluck('id');
        $profiles = UrlProfile::query()->whereIn('site_id', $siteIds)->latest('last_synced_at')->paginate(25)->through(fn (UrlProfile $profile): array => $this->profileItem($profile));

        return Inertia::render('App/UrlProfiles/Index', ['profiles' => $profiles]);
    }

    public function show(UrlProfile $urlProfile, CurrentOrganization $context): Response
    {
        abort_unless(Site::query()->where('id', $urlProfile->site_id)->where('organization_id', $context->id())->exists(), 404);
        $snapshots = $urlProfile->snapshots()->latest('captured_at')->get()->map(fn ($snapshot): array => ['hash' => $snapshot->content_hash, 'title' => $snapshot->title, 'wordCount' => $snapshot->word_count, 'capturedAt' => $snapshot->captured_at?->toIso8601String()]);

        $item = $this->profileItem($urlProfile);

        return Inertia::render('App/UrlProfiles/Show', [
            'profile' => [
                'id' => $urlProfile->id,
                'url' => $urlProfile->canonical_url,
                'type' => $item['type'],
                'status' => $item['status'],
                'metadata' => $urlProfile->metadata,
                'gsc' => $item['gsc'],
                'auditId' => $item['auditId'],
                'snapshots' => $snapshots,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function profileItem(UrlProfile $profile): array
    {
        $metadata = is_array($profile->metadata) ? $profile->metadata : (json_decode((string) ($profile->metadata ?? '{}'), true) ?? []);

        return [
            'id' => $profile->id,
            'url' => $profile->canonical_url,
            'type' => $profile->content_type,
            'status' => $profile->post_status,
            'lastSyncedAt' => $profile->last_synced_at?->toIso8601String(),
            'gsc' => $metadata['gsc'] ?? null,
            'auditId' => DB::table('money_page_audits')
                ->where('url_profile_id', $profile->id)
                ->orderByDesc('id')
                ->value('id'),
        ];
    }
}
