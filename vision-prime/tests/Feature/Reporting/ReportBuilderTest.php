<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Domains\Organization\Models\Organization;
use App\Domains\Reporting\Actions\BuildExecutiveReport;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_report_is_created_as_draft(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $id = app(BuildExecutiveReport::class)->handle($s, 'executive_seo_summary', '2026-01-01', '2026-01-31');
        $this->assertDatabaseHas('reports', ['id' => $id, 'site_id' => $s->id, 'status' => 'draft']);
    }
}
