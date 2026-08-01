<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Domains\Content\Models\UrlProfile;
use App\Domains\Organization\Contracts\CurrentOrganization;
use App\Domains\Workspace\Models\Site;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class UrlProfileController extends Controller
{
    public function index(CurrentOrganization $context): Response
    {
        $siteIds = Site::query()->where('organization_id', $context->id())->pluck('id');
        $profiles = UrlProfile::query()->whereIn('site_id', $siteIds)->latest('last_synced_at')->paginate(25)->through(fn (UrlProfile $profile): array => ['id' => $profile->id, 'url' => $profile->canonical_url, 'type' => $profile->content_type, 'status' => $profile->post_status, 'lastSyncedAt' => $profile->last_synced_at?->toIso8601String()]);

        return Inertia::render('App/UrlProfiles/Index', ['profiles' => $profiles]);
    }

    public function show(UrlProfile $urlProfile, CurrentOrganization $context): Response
    {
        abort_unless(Site::query()->where('id', $urlProfile->site_id)->where('organization_id', $context->id())->exists(), 404);
        $snapshots = $urlProfile->snapshots()->latest('captured_at')->get()->map(fn ($snapshot): array => ['hash' => $snapshot->content_hash, 'title' => $snapshot->title, 'wordCount' => $snapshot->word_count, 'capturedAt' => $snapshot->captured_at?->toIso8601String()]);

        return Inertia::render('App/UrlProfiles/Show', ['profile' => ['id' => $urlProfile->id, 'url' => $urlProfile->canonical_url, 'metadata' => $urlProfile->metadata, 'snapshots' => $snapshots]]);
    }
}
