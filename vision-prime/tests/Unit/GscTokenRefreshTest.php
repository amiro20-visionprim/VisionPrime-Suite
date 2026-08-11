<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Gsc\Services\GscMetricsClient;
use App\Domains\Gsc\Services\GscTokenService;
use App\Domains\Gsc\Services\SearchConsoleClient;
use App\Domains\Organization\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GscTokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    private object $account;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests assert the refresh/retry logic against faked Google URLs.
        // In relay mode the request URL is rewritten to the worker, which would
        // bypass the fakes and hit the network - so pin the transport to direct.
        config(['gsc.http_proxy' => null]);

        $org = Organization::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'سازمان آزمون',
            'slug' => 'org-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        $token = ['access_token' => 'old-access', 'refresh_token' => 'refresh-123', 'expires_in' => 3600, 'scope' => 'webmasters.readonly', 'token_type' => 'Bearer'];

        $this->account = (object) \DB::table('gsc_accounts')->insertGetId([
            'organization_id' => $org->getKey(),
            'google_subject' => 'sub-123',
            'email' => 'owner@example.com',
            'token_ciphertext' => Crypt::encryptString(json_encode($token)),
            'token_expires_at' => now()->subMinute(),
            'status' => 'connected',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->account = \DB::table('gsc_accounts')->first();
    }

    public function test_expired_token_is_refreshed_and_persisted(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-access', 'expires_in' => 3600, 'scope' => 'webmasters.readonly', 'token_type' => 'Bearer'], 200),
        ]);

        $token = app(GscTokenService::class)->accessToken($this->account);

        $this->assertSame('fresh-access', $token);
        $stored = json_decode(Crypt::decryptString(\DB::table('gsc_accounts')->where('id', $this->account->id)->value('token_ciphertext')), true);
        $this->assertSame('fresh-access', $stored['access_token']);
        $this->assertSame('refresh-123', $stored['refresh_token']);
        $this->assertTrue(\DB::table('gsc_accounts')->where('id', $this->account->id)->value('token_expires_at') > now()->addMinutes(30));
    }

    public function test_search_console_client_retries_with_refreshed_token_on_401(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-access', 'expires_in' => 3600, 'scope' => 'webmasters.readonly', 'token_type' => 'Bearer'], 200),
            'https://www.googleapis.com/webmasters/v3/sites' => Http::sequence()
                ->push('', 401)
                ->push(['siteEntry' => [['siteUrl' => 'sc-domain:example.com']]], 200),
        ]);

        $properties = app(SearchConsoleClient::class)->properties($this->account);

        $this->assertSame('sc-domain:example.com', $properties[0]['siteUrl']);
        Http::assertSentCount(4); // 2 token exchanges + 2 API attempts
    }

    public function test_gsc_metrics_client_retries_with_refreshed_token_on_401(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-access', 'expires_in' => 3600, 'scope' => 'webmasters.readonly', 'token_type' => 'Bearer'], 200),
            'https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Aexample.com/searchAnalytics/query' => Http::sequence()
                ->push('', 401)
                ->push(['rows' => [['keys' => ['/'], 'clicks' => 5]]], 200),
        ]);

        $rows = app(GscMetricsClient::class)->query($this->account, 'sc-domain:example.com', '2026-08-01', '2026-08-09', ['page']);

        $this->assertSame(5, $rows['rows'][0]['clicks']);
        Http::assertSentCount(4); // 2 token exchanges + 2 API attempts
    }
}
