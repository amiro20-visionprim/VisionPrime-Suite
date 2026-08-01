<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domains\Content\Actions\UpsertUrlProfile;
use App\Domains\Content\Models\ContentSnapshot;
use App\Domains\Content\Models\UrlProfile;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UrlProfileSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_unchanged_hash_does_not_create_second_snapshot(): void
    {
        $site = $this->site();
        $item = $this->item('hash-a', 'Content A');
        $action = app(UpsertUrlProfile::class);
        $action->handle($site, $item);
        $action->handle($site, $item);

        $this->assertSame(1, UrlProfile::count());
        $this->assertSame(1, ContentSnapshot::count());
    }

    public function test_changed_hash_creates_new_snapshot_for_existing_url_profile(): void
    {
        $site = $this->site();
        $action = app(UpsertUrlProfile::class);
        $action->handle($site, $this->item('hash-a', 'Content A'));
        $action->handle($site, $this->item('hash-b', 'Content B'));

        $profile = UrlProfile::firstOrFail();
        $this->assertSame('hash-b', $profile->current_hash);
        $this->assertSame(2, ContentSnapshot::where('url_profile_id', $profile->id)->count());
    }

    private function item(string $hash, string $content): array
    {
        return ['id' => 123, 'url' => 'https://example.ir/service', 'slug' => 'service', 'type' => 'page', 'status' => 'publish', 'title' => 'Service', 'meta_title' => 'Meta', 'meta_description' => 'Description', 'headings' => ['Heading'], 'word_count' => 2, 'content_hash' => $hash, 'content' => $content];
    }

    private function site(): Site
    {
        $org = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o-'.Str::random(6), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $org->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $org->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);

        return Site::query()->create(['organization_id' => $org->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);
    }
}
