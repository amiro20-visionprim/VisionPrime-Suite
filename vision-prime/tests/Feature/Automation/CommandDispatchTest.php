<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Automation\Actions\DispatchCommand;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommandDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_command_is_signed_and_dispatched(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        \DB::table('site_connections')->insert(['site_id' => $s->id, 'status' => 'connected', 'platform_url' => 'https://wp.test', 'secret_ciphertext' => Crypt::encryptString('secret'), 'created_at' => now(), 'updated_at' => now()]);
        $id = \DB::table('commands')->insertGetId(['site_id' => $s->id, 'source_type' => 'test', 'type' => 'update_meta_title', 'risk_tier' => 'R1', 'payload' => json_encode(['title' => 'new']), 'idempotency_key' => (string) Str::uuid(), 'status' => 'approved', 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        $result = app(DispatchCommand::class)->handle($id);
        $this->assertStringContainsString('/wp-json/vision-prime/v1/commands', $result['url']);
        $this->assertDatabaseHas('commands', ['id' => $id, 'status' => 'dispatched']);
        $this->assertDatabaseHas('command_execution_logs', ['command_id' => $id, 'status' => 'dispatched']);
    }
}
