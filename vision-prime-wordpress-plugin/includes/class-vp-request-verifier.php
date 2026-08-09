<?php

defined('ABSPATH') || exit;

final class VP_Request_Verifier {
    public static function verify(WP_REST_Request $request): bool|WP_Error {
        $settings = get_option('vision_prime_connector', []);
        $secret = $settings['secret'] ?? '';
        $timestamp = (string) $request->get_header('x-vp-timestamp');
        $nonce = (string) $request->get_header('x-vp-nonce');
        $signature = (string) $request->get_header('x-vp-signature');
        if ($secret === '' || $timestamp === '' || $nonce === '' || $signature === '') return new WP_Error('vision_prime_unauthorized', 'Missing connector signature.', ['status'=>401]);
        if (abs(time() - (int)$timestamp) > 300) return new WP_Error('vision_prime_expired', 'Expired connector request.', ['status'=>401]);
        $key = 'vision_prime_nonce_' . hash('sha256', $nonce);
        if (get_transient($key)) return new WP_Error('vision_prime_replay', 'Replay request rejected.', ['status'=>409]);
        // The platform signs requests with the route as matched, e.g. /vision-prime/v1/content (leading slash included).
        $path = (string) $request->get_route();
        $payload = strtoupper($request->get_method())."\n".$path."\n".$timestamp."\n".$nonce."\n".hash('sha256', $request->get_body());
        $expected = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expected, $signature)) return new WP_Error('vision_prime_invalid_signature', 'Invalid connector signature.', ['status'=>401]);
        set_transient($key, '1', 10 * MINUTE_IN_SECONDS);
        return true;
    }
}
