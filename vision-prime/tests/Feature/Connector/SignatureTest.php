<?php

declare(strict_types=1);

namespace Tests\Feature\Connector;

use App\Domains\Connector\Services\VerifyConnectorSignature;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_signature_is_accepted_and_nonce_is_recorded(): void
    {
        [$connection, $secret] = $this->connection();
        $timestamp = (string) now()->timestamp;
        $nonce = 'nonce-'.Str::random(24);
        $signature = $this->signature($secret, 'POST', 'connector/health', '{"site_id":1}', $timestamp, $nonce);

        app(VerifyConnectorSignature::class)->handle($connection, 'POST', 'connector/health', '{"site_id":1}', $timestamp, $nonce, $signature);

        $this->assertDatabaseHas('connector_nonces', ['site_connection_id' => $connection->id, 'nonce' => $nonce]);
    }

    public function test_invalid_signature_expired_timestamp_and_reused_nonce_are_rejected(): void
    {
        [$connection, $secret] = $this->connection();
        $service = app(VerifyConnectorSignature::class);
        $timestamp = (string) now()->timestamp;
        $nonce = 'nonce-'.Str::random(24);
        $valid = $this->signature($secret, 'POST', 'connector/health', '{}', $timestamp, $nonce);

        try {
            $service->handle($connection, 'POST', 'connector/health', '{}', $timestamp, $nonce, 'invalid');
            $this->fail('Invalid signature accepted.');
        } catch (ValidationException) {
        }
        try {
            $service->handle($connection, 'POST', 'connector/health', '{}', (string) now()->subMinutes(6)->timestamp, 'old-'.Str::random(8), $valid);
            $this->fail('Expired timestamp accepted.');
        } catch (ValidationException) {
        }
        $service->handle($connection, 'POST', 'connector/health', '{}', $timestamp, $nonce, $valid);
        $this->expectException(ValidationException::class);
        $service->handle($connection, 'POST', 'connector/health', '{}', $timestamp, $nonce, $valid);
    }

    /** @return array{object,string} */
    private function connection(): array
    {
        $org = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o-'.Str::random(6), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $org->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $org->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $site = Site::query()->create(['organization_id' => $org->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);
        $secret = Str::random(80);
        \DB::table('site_connections')->insert(['site_id' => $site->id, 'status' => 'connected', 'secret_ciphertext' => Crypt::encryptString($secret), 'created_at' => now(), 'updated_at' => now()]);

        return [\DB::table('site_connections')->where('site_id', $site->id)->first(), $secret];
    }

    private function signature(string $secret, string $method, string $path, string $body, string $timestamp, string $nonce): string
    {
        return hash_hmac('sha256', strtoupper($method)."\n".$path."\n".$timestamp."\n".$nonce."\n".hash('sha256', $body), $secret);
    }
}
