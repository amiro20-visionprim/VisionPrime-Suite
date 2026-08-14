<?php

declare(strict_types=1);

namespace Tests\Feature\Connector;

use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_signed_health_request_updates_connection_and_event(): void
    {
        [$site, $secret] = $this->siteAndConnection();
        $body = json_encode(['site_id' => $site->id, 'plugin_version' => '0.1.0', 'wordpress_version' => '6.7', 'php_version' => '8.2', 'rest_api' => true]);
        $timestamp = (string) now()->timestamp;
        $nonce = 'health-'.Str::random(24);
        $payload = "POST\nconnector/health\n{$timestamp}\n{$nonce}\n".hash('sha256', $body);
        $signature = hash_hmac('sha256', $payload, $secret);
        $response = $this->withHeaders(['Content-Type' => 'application/json', 'X-VP-Timestamp' => $timestamp, 'X-VP-Nonce' => $nonce, 'X-VP-Signature' => $signature])->postJson('/connector/health', json_decode($body, true));
        $response->assertOk()->assertJsonPath('status', 'ok');
        $this->assertDatabaseHas('connector_events', ['site_id' => $site->id, 'type' => 'health.checked']);
    }

    public function test_tampered_health_report_marks_connection_degraded_and_audits(): void
    {
        [$site, $secret] = $this->siteAndConnection();
        $body = json_encode([
            'site_id' => $site->id,
            'plugin_version' => '1.0.0',
            'wordpress_version' => '6.7',
            'php_version' => '8.2',
            'rest_api' => true,
            'integrity_hash' => str_repeat('a', 64),
            'tampered' => true,
        ]);
        $timestamp = (string) now()->timestamp;
        $nonce = 'health-tampered-'.Str::random(24);
        $payload = "POST\nconnector/health\n{$timestamp}\n{$nonce}\n".hash('sha256', $body);
        $signature = hash_hmac('sha256', $payload, $secret);
        $this->withHeaders(['Content-Type' => 'application/json', 'X-VP-Timestamp' => $timestamp, 'X-VP-Nonce' => $nonce, 'X-VP-Signature' => $signature])->postJson('/connector/health', json_decode($body, true))
            ->assertOk();
        $this->assertDatabaseHas('site_connections', ['site_id' => $site->id, 'status' => 'degraded']);
        $this->assertDatabaseHas('connector_events', ['site_id' => $site->id, 'type' => 'security.tamper_detected']);
    }

    private function siteAndConnection(): array
    {
        $org = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o-'.Str::random(6), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $org->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $org->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $org->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);
        $secret = Str::random(80);
        \DB::table('site_connections')->insert(['site_id' => $site->id, 'status' => 'connected', 'secret_ciphertext' => Crypt::encryptString($secret), 'created_at' => now(), 'updated_at' => now()]);

        return [$site, $secret];
    }
}
