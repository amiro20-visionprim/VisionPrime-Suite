<?php
/**
 * PHP script to generate HMAC-signed requests and test the Vision Prime connector.
 * Usage: php test-connector.php <action> [args...]
 */

$secret = '6EwyEWexjN2b3m5cGTjvAPDG8ZNW2tAdGq3ktHw6BAOg2QyFtWQGame1RknTN3YqXhZeCuVRPYFzq52M';
$platformUrl = 'http://127.0.0.1:8000';
$siteId = 2;

function signRequest(string $secret, string $method, string $path, string $body): array {
    $timestamp = (string) time();
    $nonce = 'vp-' . bin2hex(random_bytes(8));
    $bodyHash = hash('sha256', $body);
    $payload = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $bodyHash;
    $signature = hash_hmac('sha256', $payload, $secret);
    return [
        'X-VP-Timestamp' => $timestamp,
        'X-VP-Nonce' => $nonce,
        'X-VP-Signature' => $signature,
    ];
}

function signedPost(string $url, string $secret, string $path, array $body): array {
    $encoded = json_encode($body);
    $headers = signRequest($secret, 'POST', $path, $encoded);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $encoded,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => array_merge([
            'Content-Type: application/json',
            'Accept: application/json',
        ], $headers),
    ]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'body' => json_decode($response, true)];
}

$action = $argv[1] ?? 'health';

switch ($action) {
    case 'health':
        echo "=== Health Check ===\n";
        $result = signedPost("$platformUrl/connector/health", $secret, 'connector/health', [
            'site_id' => $siteId,
            'plugin_version' => '1.2.0',
            'wordpress_version' => '7.0',
            'php_version' => PHP_VERSION,
            'rest_api' => true,
            'integrity_hash' => 'cee7b615a7925aa0622d5fc7cbbcb8c06f5620c78df0f1d87a5ce46cb5957196',
            'tampered' => 0,
        ]);
        echo "Status: {$result['status']}\n";
        echo "Body: " . json_encode($result['body']) . "\n";
        break;

    case 'content':
        echo "=== Content Sync ===\n";
        $page = $argv[2] ?? '1';
        $path = "connector/content?page=$page";
        $result = signedPost("$platformUrl/$path", $secret, $path, [
            'site_id' => $siteId,
        ]);
        echo "Status: {$result['status']}\n";
        echo "Body: " . json_encode($result['body']) . "\n";
        break;

    default:
        echo "Usage: php test-connector.php [health|content]\n";
}
