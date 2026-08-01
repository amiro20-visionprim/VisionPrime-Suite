<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use App\Domains\Connector\Contracts\ConnectorContentClient;
use App\Domains\Content\Actions\UpsertUrlProfile;
use App\Domains\Content\Jobs\SyncSiteContent;
use App\Domains\Content\Models\SyncRun;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_connector_error_marks_sync_run_failed(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $run = SyncRun::create(['site_id' => $s->id, 'type' => 'content', 'status' => 'queued']);
        \DB::table('site_connections')->insert(['site_id' => $s->id, 'status' => 'connected', 'platform_url' => 'https://wp.test', 'secret_ciphertext' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $fake = new class implements ConnectorContentClient
        {
            public function get(object $c, string $p, array $q = []): array
            {
                throw new \RuntimeException('WordPress unavailable');
            }
        };
        try {
            app(SyncSiteContent::class, ['syncRunId' => $run->id])->handle($fake, app(UpsertUrlProfile::class));
        } catch (\RuntimeException) {
        }$run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertSame('WordPress unavailable', $run->error['message']);
        $this->assertNotNull($run->finished_at);
    }
}
