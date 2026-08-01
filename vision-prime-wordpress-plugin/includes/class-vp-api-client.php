<?php

defined('ABSPATH') || exit;

final class VP_API_Client {
    public static function pair(array $settings, string $token): array|WP_Error {
        $response = wp_remote_post(rtrim($settings['platform_url'], '/') . '/connector/pair', [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode(['site_id' => (int) $settings['site_id'], 'pairing_token' => $token, 'platform_url' => home_url('/'), 'plugin_version' => '0.1.0']),
        ]);
        if (is_wp_error($response)) return $response;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) !== 200 || empty($body['secret'])) return new WP_Error('vision_prime_pairing_failed', 'Pairing failed.');
        return $body;
    }

    public static function signed_health(array $settings): array|WP_Error {
        $timestamp = (string) time();
        $nonce = wp_generate_uuid4();
        $path = '/connector/health';
        $body = wp_json_encode(['site_id'=>(int)$settings['site_id'],'plugin_version'=>'0.1.0','wordpress_version'=>get_bloginfo('version'),'php_version'=>PHP_VERSION,'rest_api'=>true]);
        $payload = "POST\n{$path}\n{$timestamp}\n{$nonce}\n" . hash('sha256', $body);
        $signature = hash_hmac('sha256', $payload, $settings['secret']);
        return wp_remote_post(rtrim($settings['platform_url'], '/') . $path, ['timeout'=>20,'headers'=>['Content-Type'=>'application/json','X-VP-Timestamp'=>$timestamp,'X-VP-Nonce'=>$nonce,'X-VP-Signature'=>$signature],'body'=>$body]);
    }
}
