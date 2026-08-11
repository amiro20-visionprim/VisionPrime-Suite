<?php

declare(strict_types=1);

namespace App\Domains\Gsc\Services;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

/**
 * Central HTTP layer for every Google API call the GSC domain makes.
 *
 * Three transport modes, selected through GSC_HTTP_PROXY:
 *
 *  - not set ............ direct connection (used in tests / outside Iran)
 *  - regular proxy ...... e.g. http://127.0.0.1:12334 (Hiddify). Guzzle's
 *                         native "proxy" option is used (CONNECT based).
 *  - Cloudflare Worker .. proxy host ending with ".workers.dev". Cloudflare
 *                         Workers reject the CONNECT method, so a classic
 *                         proxy cannot work there. Instead the request URI
 *                         is rewritten to https://<worker>/?target=<base64url>
 *                         and the Worker performs the real call from
 *                         Cloudflare IPs, returning the upstream response
 *                         unchanged.
 */
final class GscHttp
{
    /**
     * Build a PendingRequest wired for the configured transport.
     */
    public function request(array $headers = [], array $options = []): PendingRequest
    {
        $http = Http::withHeaders($headers)->withOptions($options)->timeout(60);

        $proxy = config('gsc.http_proxy');

        if (! $proxy) {
            return $http;
        }

        if (self::isRelay($proxy)) {
            $stack = HandlerStack::create();
            $stack->push(Middleware::mapRequest(
                fn (RequestInterface $request): RequestInterface => $request->withUri(new Uri(self::relayTargetUrl((string) $request->getUri(), $proxy)))
            ));

            $http = $http->withOptions(['handler' => $stack]);

            if ($token = config('gsc.relay_token')) {
                $http = $http->withHeaders(['x-relay-token' => $token]);
            }

            return $http;
        }

        return $http->withOptions(['proxy' => $proxy]);
    }

    /**
     * True when the configured proxy is one of our Cloudflare relay workers.
     */
    public static function isRelay(string $proxy): bool
    {
        $host = str_contains($proxy, '://')
            ? (string) parse_url($proxy, PHP_URL_HOST)
            : $proxy;

        return $host === 'workers.dev' || str_ends_with($host, '.workers.dev');
    }

    /**
     * Rewrite an absolute target URL into the relay worker's ?target= format.
     */
    public static function relayTargetUrl(string $target, string $proxy): string
    {
        $base = rtrim($proxy, '/');
        if (! preg_match('#^https?://#i', $base)) {
            $base = 'https://'.$base;
        }

        $encoded = rtrim(strtr(base64_encode($target), '+/', '-_'), '=');

        return str_contains($base, '?')
            ? $base.'&target='.$encoded
            : $base.'/relay?target='.$encoded;
    }
}
