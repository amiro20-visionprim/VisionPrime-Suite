<?php

declare(strict_types=1);

namespace Tests\Feature\Automation;

use App\Domains\Automation\Actions\CreateRollbackSnapshot;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class RollbackSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_is_encrypted_and_available(): void
    {
        $o = Organization::create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o', 'status' => 'active']);
        $c = Client::create(['organization_id' => $o->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $p = Project::create(['organization_id' => $o->id, 'client_id' => $c->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $s = Site::create(['organization_id' => $o->id, 'project_id' => $p->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://e.ir', 'status' => 'active']);
        $command = \DB::table('commands')->insertGetId(['site_id' => $s->id, 'source_type' => 'test', 'type' => 'update_meta_title', 'risk_tier' => 'R1', 'payload' => '{}', 'idempotency_key' => (string) Str::uuid(), 'status' => 'approved', 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);
        $id = app(CreateRollbackSnapshot::class)->handle($command, 'post:1', ['title' => 'old']);
        $snapshot = \DB::table('rollback_snapshots')->where('id', $id)->first();
        $this->assertSame('available', $snapshot->status);
        $this->assertNotSame(json_encode(['title' => 'old']), $snapshot->snapshot_ciphertext);
        $this->assertSame(['title' => 'old'], json_decode(Crypt::decryptString($snapshot->snapshot_ciphertext), true));
    }
}
