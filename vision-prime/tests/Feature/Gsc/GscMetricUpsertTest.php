<?php

declare(strict_types=1);

namespace Tests\Feature\Gsc;

use App\Domains\Gsc\Actions\UpsertGscMetric;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GscMetricUpsertTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_query_and_query_page_metrics_are_upserted(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $a = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $o->id, 'google_subject' => 'sub', 'email' => 'a@test', 'token_ciphertext' => 'x', 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $property = \DB::table('gsc_properties')->insertGetId(['site_id' => $s->id, 'gsc_account_id' => $a, 'property_uri' => 'https://e.ir', 'property_type' => 'url-prefix', 'created_at' => now(), 'updated_at' => now()]);
        $u = app(UpsertGscMetric::class);
        $u->page($property, '2026-01-01', ['keys' => ['https://e.ir/a'], 'clicks' => 3, 'impressions' => 10, 'ctr' => .3, 'position' => 2]);
        $u->query($property, '2026-01-01', ['keys' => ['seo'], 'clicks' => 2, 'impressions' => 8, 'ctr' => .25, 'position' => 3]);
        $u->queryPage($property, '2026-01-01', ['keys' => ['seo', 'https://e.ir/a'], 'clicks' => 2, 'impressions' => 8, 'ctr' => .25, 'position' => 3]);
        $this->assertDatabaseCount('gsc_page_metrics', 1);
        $this->assertDatabaseCount('gsc_query_metrics', 1);
        $this->assertDatabaseCount('gsc_query_page_metrics', 1);
    }
}
