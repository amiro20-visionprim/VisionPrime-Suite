<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domains\Connector\Contracts\ConnectorContentClient;
use App\Domains\Content\Actions\UpsertUrlProfile;
use App\Domains\Content\Jobs\SyncSiteContent;
use App\Domains\Content\Models\SyncRun;
use App\Domains\Content\Models\SyncRunItem;
use App\Domains\Content\Models\UrlProfile;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_imports_all_pages(): void
    {
        $site = $this->site();
        $run = SyncRun::create(['site_id' => $site->id, 'type' => 'content', 'status' => 'queued']);
        \DB::table('site_connections')->insert(['site_id' => $site->id, 'status' => 'connected', 'platform_url' => 'https://wp.test', 'secret_ciphertext' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $this->app->bind(ConnectorContentClient::class, fn () => new class implements ConnectorContentClient
        {
            public function get(object $c, string $p, array $q = []): array
            {
                $page = $q['page'];
                $item = fn ($id) => ['id' => $id, 'url' => "https://example.ir/p{$id}", 'slug' => "p{$id}", 'type' => 'page', 'status' => 'publish', 'title' => "P{$id}", 'headings' => [], 'word_count' => 1, 'content_hash' => "h{$id}", 'content' => "C{$id}"];

                return $page === 1 ? ['data' => [$item(1), $item(2)], 'total_pages' => 2] : ['data' => [$item(3)], 'total_pages' => 2];
            }
        });
        app(SyncSiteContent::class, ['syncRunId' => $run->id])->handle(app(ConnectorContentClient::class), app(UpsertUrlProfile::class));
        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame(3, UrlProfile::count());
        $this->assertSame(3, SyncRunItem::count());
    }

    private function site(): Site
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);

        return Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
    }
}
