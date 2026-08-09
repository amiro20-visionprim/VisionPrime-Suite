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

class CommandResultCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_result_callback_updates_command_and_log(): void
    {
        [$connection, $secret, $site] = $this->connection();
        $idempotencyKey = (string) Str::uuid();
        $commandId = \DB::table('commands')->insertGetId([
            'site_id' => $site->id,
            'source_type' => 'test',
            'type' => 'update_meta_title',
            'risk_tier' => 'R1',
            'payload' => json_encode([]),
            'idempotency_key' => $idempotencyKey,
            'status' => 'dispatched',
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('command_execution_logs')->insert([
            'command_id' => $commandId,
            'attempt' => 1,
            'status' => 'dispatched',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $body = json_encode([
            'site_id' => $site->id,
            'idempotency_key' => $idempotencyKey,
            'status' => 'executed',
            'result' => ['new_title' => 'عنوان جدید'],
        ]);
        $timestamp = (string) now()->timestamp;
        $nonce = 'nonce-'.Str::random(24);
        $signature = hash_hmac('sha256', "POST\nconnector/command-result\n{$timestamp}\n{$nonce}\n".hash('sha256', $body), $secret);

        $this->postJson('/connector/command-result', json_decode($body, true), [
            'X-VP-Timestamp' => $timestamp,
            'X-VP-Nonce' => $nonce,
            'X-VP-Signature' => $signature,
        ])->assertOk()->assertJson(['status' => 'ack']);

        $this->assertDatabaseHas('commands', ['id' => $commandId, 'status' => 'executed']);
        $this->assertDatabaseHas('command_execution_logs', ['command_id' => $commandId, 'status' => 'executed']);
        $this->assertDatabaseHas('connector_events', ['site_id' => $site->id, 'type' => 'command.result_received']);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        [$connection, $secret, $site] = $this->connection();
        $body = json_encode(['site_id' => $site->id, 'idempotency_key' => 'k', 'status' => 'executed']);
        $timestamp = (string) now()->timestamp;
        $nonce = 'nonce-'.Str::random(24);

        $this->postJson('/connector/command-result', json_decode($body, true), [
            'X-VP-Timestamp' => $timestamp,
            'X-VP-Nonce' => $nonce,
            'X-VP-Signature' => 'bad-signature',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('connector_events', ['site_id' => $site->id, 'type' => 'command.result_received']);
    }

    /** @return array{object,string,Site} */
    private function connection(): array
    {
        $org = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o-'.Str::random(6), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $org->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $org->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $org->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);
        $secret = Str::random(80);
        \DB::table('site_connections')->insert(['site_id' => $site->id, 'status' => 'connected', 'platform_url' => 'https://wp.test', 'secret_ciphertext' => Crypt::encryptString($secret), 'created_at' => now(), 'updated_at' => now()]);

        return [\DB::table('site_connections')->where('site_id', $site->id)->first(), $secret, $site];
    }
}
