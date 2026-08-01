<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Contracts\CurrentClient;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientReportScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_scope_contains_only_published_reports(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $a = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'A', 'status' => 'active']);
        $b = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'B', 'status' => 'active']);
        foreach ([[$a, 'published'], [$a, 'draft'], [$b, 'published']] as [$client,$status]) {
            $p = Project::create(['organization_id' => $o->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => Str::random(5), 'status' => 'active']);
            $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => Str::random(5), 'canonical_url' => 'https://'.Str::random(5).'.ir', 'status' => 'active']);
            \DB::table('reports')->insert(['site_id' => $s->id, 'type' => 'monthly', 'period_start' => '2026-01-01', 'period_end' => '2026-01-31', 'status' => $status, 'content' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        }$scope = app(CurrentClient::class);
        $scope->set($a);
        $siteIds = \DB::table('sites')->join('projects', 'projects.id', '=', 'sites.project_id')->where('projects.client_id', $scope->id())->pluck('sites.id');
        $this->assertSame(1, \DB::table('reports')->whereIn('site_id', $siteIds)->where('status', 'published')->count());
    }
}
