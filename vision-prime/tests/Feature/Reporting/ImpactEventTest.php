<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Domains\Organization\Models\Organization;
use App\Domains\Reporting\Actions\CreateImpactEvent;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImpactEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_impact_event_stores_baseline_and_attribution_note(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $id = app(CreateImpactEvent::class)->handle($s, 'command', 10, ['clicks' => 100, 'ctr' => .02], 'Observed after approved change.');
        $this->assertDatabaseHas('impact_events', ['id' => $id, 'site_id' => $s->id, 'source_type' => 'command', 'source_id' => 10]);
    }
}
