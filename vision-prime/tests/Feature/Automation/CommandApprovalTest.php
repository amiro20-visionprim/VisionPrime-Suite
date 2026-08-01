<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Automation\Actions\ApproveCommand;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommandApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_command_moves_from_pending_to_approved(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $u = User::factory()->create();
        $id = \DB::table('commands')->insertGetId(['site_id' => $site->id, 'source_type' => 'test', 'type' => 'update_meta_title', 'risk_tier' => 'R1', 'payload' => json_encode([]), 'idempotency_key' => (string) Str::uuid(), 'status' => 'pending_approval', 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        app(ApproveCommand::class)->handle($id, $u, 'approved', 'ok');
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'approved']);
        $this->assertDatabaseHas('command_approvals', ['command_id' => $id, 'decision' => 'approved']);
    }
}
