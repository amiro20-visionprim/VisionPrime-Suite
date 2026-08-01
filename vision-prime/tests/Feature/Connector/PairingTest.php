<?php

declare(strict_types=1);

namespace Tests\Feature\Connector;

use App\Domains\Connector\Actions\ConsumePairingToken;
use App\Domains\Connector\Actions\CreatePairingToken;
use App\Domains\Organization\Models\Organization;
use App\Domains\Workspace\Models\Client;
use App\Domains\Workspace\Models\Project;
use App\Domains\Workspace\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PairingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pairing_token_is_single_use_and_connection_secret_is_encrypted(): void
    {
        $site = $this->site();
        $pairing = app(CreatePairingToken::class)->handle($site);
        $result = app(ConsumePairingToken::class)->handle($site, $pairing['token'], 'https://wp.example.ir', '0.1.0');

        $connection = \DB::table('site_connections')->where('site_id', $site->id)->first();
        $token = \DB::table('pairing_tokens')->where('site_id', $site->id)->first();

        $this->assertSame('connected', $connection->status);
        $this->assertNotSame($result['secret'], $connection->secret_ciphertext);
        $this->assertSame($result['secret'], Crypt::decryptString($connection->secret_ciphertext));
        $this->assertNotNull($token->consumed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'connector.paired', 'subject_id' => $site->id]);
    }

    public function test_expired_pairing_token_is_rejected(): void
    {
        $site = $this->site();
        \DB::table('pairing_tokens')->insert(['site_id' => $site->id, 'token_hash' => Hash::make('x'), 'expires_at' => now()->subMinute(), 'created_at' => now(), 'updated_at' => now()]);
        $this->expectException(ValidationException::class);
        app(ConsumePairingToken::class)->handle($site, 'x', 'https://wp.example.ir', '0.1.0');
    }

    private function site(): Site
    {
        $org = Organization::query()->create(['public_id' => (string) Str::ulid(), 'name' => 'O', 'slug' => 'o-'.Str::random(6), 'status' => 'active']);
        $client = Client::query()->create(['organization_id' => $org->id, 'public_id' => (string) Str::ulid(), 'name' => 'C', 'status' => 'active']);
        $project = Project::query()->create(['organization_id' => $org->id, 'client_id' => $client->id, 'public_id' => (string) Str::ulid(), 'name' => 'P', 'status' => 'active']);

        return Site::query()->create(['organization_id' => $org->id, 'project_id' => $project->id, 'public_id' => (string) Str::ulid(), 'name' => 'S', 'canonical_url' => 'https://example.ir', 'status' => 'active']);
    }
}
