<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Gsc\Services\GscHttp;
use Tests\TestCase;

class GscRelayTest extends TestCase
{
    public function test_is_relay_detects_workers_dev_hosts(): void
    {
        $this->assertTrue(GscHttp::isRelay('https://visionprime-gsc-proxy.user.workers.dev'));
        $this->assertTrue(GscHttp::isRelay('visionprime-gsc-proxy.user.workers.dev'));
        $this->assertTrue(GscHttp::isRelay('https://worker.workers.dev'));
    }

    public function test_is_relay_rejects_regular_proxies(): void
    {
        $this->assertFalse(GscHttp::isRelay('http://127.0.0.1:12334'));
        $this->assertFalse(GscHttp::isRelay('https://proxy.example.com'));
        $this->assertFalse(GscHttp::isRelay(''));
    }

    public function test_relay_target_url_roundtrips_the_original_url(): void
    {
        $proxy = 'https://visionprime-gsc-proxy.user.workers.dev';
        $target = 'https://www.googleapis.com/webmasters/v3/sites/sc-domain%3Aliuna.ir/searchAnalytics/query?startDate=2026-08-01';        $relayed = GscHttp::relayTargetUrl($target, $proxy);
        $this->assertStringStartsWith($proxy.'/relay?target=', $relayed);
        $encoded = substr($relayed, strlen($proxy.'/relay?target='));
        $b64 = strtr($encoded, '-_', '+/');
        $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
        $this->assertSame($target, base64_decode($b64, true));
    }

    public function test_relay_target_url_adds_scheme_when_missing(): void
    {
        $relayed = GscHttp::relayTargetUrl(
            'https://oauth2.googleapis.com/token',
            'visionprime-gsc-proxy.user.workers.dev'
        );

        $this->assertStringStartsWith('https://visionprime-gsc-proxy.user.workers.dev/relay?target=', $relayed);
    }
}
