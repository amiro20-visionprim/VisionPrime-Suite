<?php

declare(strict_types=1);

namespace App\Domains\Content\Actions;

use App\Domains\Content\Models\ContentSnapshot;
use App\Domains\Content\Models\UrlProfile;
use App\Domains\Workspace\Models\Site;
use Illuminate\Support\Str;

class UpsertUrlProfile
{
    public function handle(Site $site, array $item): UrlProfile
    {
        $profile = UrlProfile::query()->firstOrNew(['site_id' => $site->id, 'canonical_url' => $item['url']]);
        $changed = $profile->current_hash !== ($item['content_hash'] ?? null);
        $profile->fill(['public_id' => $profile->public_id ?: (string) Str::ulid(), 'external_content_id' => (string) $item['id'], 'slug' => $item['slug'] ?? null, 'content_type' => $item['type'], 'post_status' => $item['status'], 'metadata' => ['meta_title' => $item['meta_title'] ?? null, 'meta_description' => $item['meta_description'] ?? null], 'current_hash' => $item['content_hash'] ?? null, 'last_synced_at' => now()])->save();
        if ($changed) {
            ContentSnapshot::query()->firstOrCreate(['url_profile_id' => $profile->id, 'content_hash' => $item['content_hash']], ['title' => $item['title'] ?? null, 'meta' => ['meta_title' => $item['meta_title'] ?? null, 'meta_description' => $item['meta_description'] ?? null], 'headings' => $item['headings'] ?? [], 'content' => $item['content'] ?? null, 'word_count' => (int) ($item['word_count'] ?? 0), 'captured_at' => now()]);
        }

        return $profile;
    }
}
