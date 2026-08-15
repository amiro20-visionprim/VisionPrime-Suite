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

/**
 * تست Replay Attack در سطح HTTP: یک درخواست امضاشدهٔ معتبر که با همان nonce
 * یا با timestamp منقضی دوباره ارسال شود، باید توسط endpoint رد شود.
 */
class ReplayAttackTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o-'.Str::random(6), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $org->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $org->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);
        $this->site = Site::query()->create(['organization_id' => $org->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);
        $this->secret = Str::random(80);
        \DB::table('site_connections')->insert(['site_id' => $this->site->id, 'status' => 'connected', 'platform_url' => 'https://wp.test', 'secret_ciphertext' => Crypt::encryptString($this->secret), 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @return array{headers: array<string,string>, body: array<string,mixed>} */
    private function signedHealth(string $timestamp, string $nonce, array $extra = []): array
    {
        $body = array_merge(['site_id' => $this->site->id, 'plugin_version' => '1.0.0', 'wordpress_version' => '7.0', 'php_version' => '8.3', 'rest_api' => true], $extra);
        $encoded = json_encode($body);
        $payload = "POST\nconnector/health\n{$timestamp}\n{$nonce}\n".hash('sha256', $encoded);
        $signature = hash_hmac('sha256', $payload, $this->secret);

        return [
            'headers' => ['Content-Type' => 'application/json', 'X-VP-Timestamp' => $timestamp, 'X-VP-Nonce' => $nonce, 'X-VP-Signature' => $signature],
            'body' => $body,
        ];
    }

    public function test_replaying_a_valid_request_with_same_nonce_is_rejected(): void
    {
        $timestamp = (string) now()->timestamp;
        $nonce = 'replay-'.Str::random(24);
        $signed = $this->signedHealth($timestamp, $nonce);

        // اول: درخواست معتبر پذیرفته می‌شود.
        $this->withHeaders($signed['headers'])->postJson('/connector/health', $signed['body'])->assertOk();

        // بعد: همان درخواست با همان nonce/timestamp — باید رد شود (replay).
        $replay = $this->withHeaders($signed['headers'])->postJson('/connector/health', $signed['body']);
        $replay->assertStatus(422);
        $replay->assertJsonValidationErrors('nonce');
    }

    public function test_request_with_expired_timestamp_is_rejected_even_with_valid_signature(): void
    {
        $expired = (string) now()->subMinutes(6)->timestamp;
        $signed = $this->signedHealth($expired, 'expired-'.Str::random(24));

        $response = $this->withHeaders($signed['headers'])->postJson('/connector/health', $signed['body']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('timestamp');
    }

    public function test_request_without_signature_headers_is_rejected(): void
    {
        $this->postJson('/connector/health', ['site_id' => $this->site->id])->assertStatus(422);
    }

    public function test_replayed_nonce_from_a_different_site_is_not_rejected_across_sites(): void
    {
        // nonce فقط برای همان اتصال معتبر است؛ اتصال دیگر با nonce یکسان نباید بلاک شود.
        $org2 = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'O2', 'slug' => 'o2-'.Str::random(6), 'status' => 'active']);
        $client2 = Client::query()->create(['organization_id' => $org2->id, 'public_id' => (string) Str::ulid(), 'name' => 'C2', 'status' => 'active']);
        $project2 = Project::query()->create(['organization_id' => $org2->id, 'client_id' => $client2->id, 'public_id' => (string) Str::ulid(), 'name' => 'P2', 'status' => 'active']);
        $site2 = Site::query()->create(['organization_id' => $org2->id, 'project_id' => $project2->id, 'public_id' => (string) Str::ulid(), 'name' => 'S2', 'canonical_url' => 'https://other.ir', 'status' => 'active']);
        \DB::table('site_connections')->insert(['site_id' => $site2->id, 'status' => 'connected', 'platform_url' => 'https://wp2.test', 'secret_ciphertext' => Crypt::encryptString($this->secret), 'created_at' => now(), 'updated_at' => now()]);

        $timestamp = (string) now()->timestamp;
        $nonce = 'cross-site-'.Str::random(24);

        $signed1 = $this->signedHealth($timestamp, $nonce);
        $this->withHeaders($signed1['headers'])->postJson('/connector/health', $signed1['body'])->assertOk();

        $body2 = ['site_id' => $site2->id, 'plugin_version' => '1.0.0', 'wordpress_version' => '7.0', 'php_version' => '8.3', 'rest_api' => true];
        $payload2 = "POST\nconnector/health\n{$timestamp}\n{$nonce}\n".hash('sha256', json_encode($body2));
        $signature2 = hash_hmac('sha256', $payload2, $this->secret);

        $this->withHeaders(['Content-Type' => 'application/json', 'X-VP-Timestamp' => $timestamp, 'X-VP-Nonce' => $nonce, 'X-VP-Signature' => $signature2])
            ->postJson('/connector/health', $body2)
            ->assertOk();
    }
}
