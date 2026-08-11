<?php

declare(strict_types=1);

namespace Tests\Feature\Gsc;

use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class GscImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeSite(string $url): Site
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o'.Str::random(4), 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => $url, 'status' => 'active']);
        $a = \DB::table('gsc_accounts')->insertGetId(['organization_id' => $o->id, 'google_subject' => 'sub', 'email' => 'a@test', 'token_ciphertext' => 'x', 'status' => 'connected', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('gsc_properties')->insertGetId(['site_id' => $s->id, 'gsc_account_id' => $a, 'property_uri' => $url, 'property_type' => 'url-prefix', 'created_at' => now(), 'updated_at' => now()]);

        return $s;
    }

    public function test_command_queues_imports_for_all_connected_sites(): void
    {
        Queue::fake();

        $a = $this->makeSite('https://a.ir');
        $b = $this->makeSite('https://b.ir');

        $this->artisan('gsc:import', ['--days' => 7])
            ->expectsOutputToContain('2 import در صف قرار گرفت')
            ->assertExitCode(0);

        Queue::assertPushed(\App\Domains\Gsc\Jobs\ImportGscMetrics::class, 2);
        $this->assertDatabaseHas('gsc_import_runs', ['gsc_property_id' => \DB::table('gsc_properties')->where('site_id', $a->id)->value('id'), 'status' => 'queued']);
        $this->assertDatabaseHas('gsc_import_runs', ['gsc_property_id' => \DB::table('gsc_properties')->where('site_id', $b->id)->value('id'), 'status' => 'queued']);
    }

    public function test_command_filters_by_site_option(): void
    {
        Queue::fake();

        $a = $this->makeSite('https://a.ir');
        $this->makeSite('https://b.ir');

        $this->artisan('gsc:import', ['--site' => (string) $a->id, '--days' => 3])->assertExitCode(0);

        Queue::assertPushed(\App\Domains\Gsc\Jobs\ImportGscMetrics::class, 1);
        $this->assertDatabaseCount('gsc_import_runs', 1);
    }

    public function test_command_reports_when_nothing_is_connected(): void
    {
        $this->artisan('gsc:import')
            ->expectsOutputToContain('هیچ ملک سرچ کنسولی متصل نیست')
            ->assertExitCode(0);
    }
}
