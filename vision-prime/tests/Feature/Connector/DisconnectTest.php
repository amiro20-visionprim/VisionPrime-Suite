<?php

declare(strict_types=1);

namespace Tests\Feature\Connector;

use App\Domains\Connector\Actions\DisconnectSite;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class DisconnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_disconnect_clears_secret_nonce_health_and_records_audit(): void
    {
        $org = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o-'.Str::random(6), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $org->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $org->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $org->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);
        \DB::table('site_connections')->insert(['site_id' => $site->id, 'status' => 'connected', 'secret_ciphertext' => Crypt::encryptString('secret'), 'last_seen_at' => now(), 'health' => json_encode(['rest_api' => true]), 'created_at' => now(), 'updated_at' => now()]);
        $connection = \DB::table('site_connections')->where('site_id', $site->id)->first();
        \DB::table('connector_nonces')->insert(['site_connection_id' => $connection->id, 'nonce' => 'nonce-1', 'expires_at' => now()->addMinute(), 'used_at' => now()]);

        app(DisconnectSite::class)->handle($site);

        $this->assertDatabaseHas('site_connections', ['site_id' => $site->id, 'status' => 'disconnected', 'secret_ciphertext' => null, 'last_seen_at' => null]);
        $this->assertDatabaseMissing('connector_nonces', ['site_connection_id' => $connection->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'connector.disconnected', 'subject_id' => $site->id]);
    }
}
