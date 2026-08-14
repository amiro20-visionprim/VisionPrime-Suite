<?php

defined('ABSPATH') || exit;

final class VP_API_Client {
    /**
     * Pair this site with the Vision Prime platform.
     *
     * @param array $settings Plugin settings (platform_url, site_id).
     * @return array|WP_Error Response body with `secret` on success.
     */
    public static function pair(array $settings, string $token): array|WP_Error {
        $response = wp_remote_post(rtrim($settings['platform_url'], '/') . '/connector/pair', [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['site_id' => (int) $settings['site_id'], 'pairing_token' => $token, 'platform_url' => home_url('/'), 'plugin_version' => VISION_PRIME_CONNECTOR_VERSION]),
        ]);
        if (is_wp_error($response)) return $response;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) !== 200 || empty($body['secret'])) return new WP_Error('vision_prime_pairing_failed', 'Pairing failed.');
        return $body;
    }

    /**
     * Sign a request body for the platform (HMAC-SHA256 over method, path, timestamp, nonce, body hash).
     *
     * The platform verifies with Laravel's request->path(), which has NO leading slash,
     * so the path must be signed without one (e.g. `connector/health`).
     *
     * Refuses to sign when the connector file has been tampered with.
     *
     * @return array{headers: array, body: string}|WP_Error
     */
    public static function signed_request(array $settings, string $method, string $path, array $body): array|WP_Error {
        if (VP_Guard::tampered()) {
            return new WP_Error('vision_prime_tampered', 'Connector integrity check failed; refusing to sign.');
        }
        $timestamp = (string) time();
        $nonce = wp_generate_uuid4();
        $encoded = wp_json_encode($body);
        $payload = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $encoded);
        $signature = hash_hmac('sha256', $payload, $settings['secret']);
        return [
            'headers' => ['Content-Type' => 'application/json', 'X-VP-Timestamp' => $timestamp, 'X-VP-Nonce' => $nonce, 'X-VP-Signature' => $signature],
            'body' => $encoded,
        ];
    }

    public static function signed_health(array $settings): array|WP_Error {
        $signed = self::signed_request($settings, 'POST', 'connector/health', [
            'site_id' => (int) $settings['site_id'],
            'plugin_version' => VISION_PRIME_CONNECTOR_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'rest_api' => true,
            'integrity_hash' => VP_Guard::file_hash(),
            'tampered' => VP_Guard::is_tampered_flag(),
        ]);
        if (is_wp_error($signed)) return $signed;
        return wp_remote_post(rtrim($settings['platform_url'], '/') . '/connector/health', ['timeout' => 20, 'headers' => $signed['headers'], 'body' => $signed['body']]);
    }

    /**
     * Report a command execution result back to the platform.
     *
     * @param array $settings Plugin settings.
     * @param array{site_id:int,idempotency_key:string,status:string,result:?array,error:?string} $result
     * @return array|WP_Error wp_remote_post response.
     */
    public static function send_command_result(array $settings, array $result): array|WP_Error {
        $result['integrity_hash'] = VP_Guard::file_hash();
        $result['tampered'] = VP_Guard::is_tampered_flag();
        $signed = self::signed_request($settings, 'POST', 'connector/command-result', $result);
        if (is_wp_error($signed)) return $signed;
        return wp_remote_post(rtrim($settings['platform_url'], '/') . '/connector/command-result', ['timeout' => 20, 'headers' => $signed['headers'], 'body' => $signed['body']]);
    }
}
