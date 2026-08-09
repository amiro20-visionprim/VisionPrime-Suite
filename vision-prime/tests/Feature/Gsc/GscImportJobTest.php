<?php

declare(strict_types=1);

namespace Tests\Feature\Gsc;

use App\Domains\Gsc\Actions\UpsertGscMetric;
use App\Domains\Gsc\Jobs\ImportGscMetrics;
use App\Domains\Gsc\Services\GscMetricsClient;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GscImportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_job_stores_all_dimension_rows(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $a = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $o->id, 'google_subject' => 'sub', 'email' => 'a@test', 'token_ciphertext' => 'x', 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        $property = \DB::table('gsc_properties')->insertGetId(['site_id' => $s->id, 'gsc_account_id' => $a, 'property_uri' => 'https://e.ir', 'property_type' => 'url-prefix', 'created_at' => now(), 'updated_at' => now()]);
        $run = \DB::table('gsc_import_runs')->insertGetId(['gsc_property_id' => $property, 'date_start' => '2026-01-01', 'date_end' => '2026-01-02', 'status' => 'queued', 'created_at' => now(), 'updated_at' => now()]);
        $fake = new class(app(\App\Domains\Gsc\Services\GscTokenService::class)) extends GscMetricsClient
        {
            public function __construct(\App\Domains\Gsc\Services\GscTokenService $tokens)
            {
                parent::__construct($tokens);
            }

            public function query(object $a, string $p, string $s, string $e, array $d): array
            {
                return ['rows' => [['keys' => count($d) === 2 ? ['seo', 'https://e.ir/a'] : [($d[0] === 'page' ? 'https://e.ir/a' : 'seo')], 'clicks' => 1, 'impressions' => 2, 'ctr' => .5, 'position' => 3]]];
            }
        };
        app(ImportGscMetrics::class, ['importRunId' => $run])->handle($fake, app(UpsertGscMetric::class));
        $this->assertDatabaseHas('gsc_import_runs', ['id' => $run, 'status' => 'completed']);
        $this->assertDatabaseCount('gsc_page_metrics', 1);
        $this->assertDatabaseCount('gsc_query_metrics', 1);
        $this->assertDatabaseCount('gsc_query_page_metrics', 1);
    }
}
