<?php

declare(strict_types=1);

/**
 * Standalone Rank Math scenario for the plugin test harness.
 *
 * Boots the encoded distribution in a fresh process with SEO engine(s)
 * "active" (via defined constants) and verifies the meta commands write to
 * the right meta keys and the content endpoint reads Rank Math first.
 *
 * Usage: php tests/run-rankmath-scenario.php <path-to-dist>
 * Mode:  VP_ENGINE=rank_math (default) | both
 * Output: "OK" on success, details otherwise.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

if ($argc < 2 || ! is_file($argv[1])) {
    fwrite(STDERR, "Usage: php run-rankmath-scenario.php <dist.php>\n");
    exit(1);
}
$dist = $argv[1];

define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);

$engine = getenv('VP_ENGINE') ?: 'rank_math';
if ($engine === 'rank_math' || $engine === 'both') {
    define('RANK_MATH_VERSION', '1.0.234');
}
if ($engine === 'both') {
    define('WPSEO_VERSION', '24.0');
}

class WP_Error
{
    private string $code;
    public function __construct(string $code = '', string $message = '')
    {
        $this->code = $code;
    }
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return ''; }
}

function &vp_store(): array
{
    static $s = ['options' => [], 'transients' => [], 'meta' => [], 'posts' => [], 'http' => [], 'hooks' => [], 'routes' => []];
    return $s;
}
function get_option(string $k, mixed $d = false): mixed { $s =& vp_store(); return array_key_exists($k, $s['options']) ? $s['options'][$k] : $d; }
function update_option(string $k, mixed $v, bool $autoload = true): bool { $s =& vp_store(); $s['options'][$k] = $v; return true; }
function get_transient(string $k): mixed { return false; }
function set_transient(string $k, mixed $v, int $exp): bool { return true; }
function add_action(): void {}
function add_options_page(): void {}
function add_menu_page(): void {}
function register_setting(): void {}
function register_rest_route(): void {}
function current_user_can(): bool { return true; }
function wp_salt(string $scheme = 'auth'): string { return 'scenario-salt-abcdef0123456789'; }
function wp_generate_uuid4(): string
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x0fff) | 0x4000, random_int(0, 0x3fff) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
}
function sanitize_text_field(string $s): string { return trim($s); }
function sanitize_key(string $s): string { return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($s)); }
function absint(mixed $v): int { return abs((int) $v); }
function wp_strip_all_tags(string $s): string { return strip_tags($s); }
function wp_json_encode(mixed $v, int $options = 0, int $depth = 512): string|false
{
    return json_encode($v, $options, $depth);
}
function wp_remote_post(string $url, array $args = []): array { $s =& vp_store(); $s['http'][] = ['url' => $url, 'args' => $args]; return ['response' => ['code' => 200], 'body' => '{"ok":true}']; }
function wp_remote_retrieve_body(array $r): string { return $r['body'] ?? ''; }
function wp_remote_retrieve_response_code(array $r): int { return $r['response']['code'] ?? 200; }
function is_wp_error(mixed $x): bool { return $x instanceof WP_Error; }
function get_bloginfo(string $f = ''): string { return '6.7'; }
function home_url(string $p = ''): string { return 'http://example.test' . $p; }
function esc_url_raw(string $u): string { return $u; }

class WP_REST_Request
{
    private array $params;
    private array $headers;
    public function __construct(string $method, string $route, string $body, array $headers, array $params)
    {
        $this->headers = $headers;
        $this->params = $params;
    }
    public function get_header(string $k): ?string { return $this->headers[$k] ?? null; }
    public function get_json_params(): array { return $this->params; }
    public function get_param(string $k): mixed { return $this->params[$k] ?? null; }
}

class WP_REST_Response
{
    public mixed $data;
    public int $status;
    public function __construct(mixed $data = [], int $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }
}

class WP_Post
{
    public int $ID;
    public string $post_content = '';
    public string $post_name = '';
    public string $post_type = 'post';
    public string $post_status = 'publish';
}

class WP_Query
{
    public array $posts = [];
    public int $found_posts = 0;
    public int $max_num_pages = 0;
    public function __construct(array $args = [])
    {
        $s =& vp_store();
        $this->posts = $s['posts'];
        $this->found_posts = count($s['posts']);
        $this->max_num_pages = 1;
    }
}

function get_the_title(WP_Post $p): string { return 'Title ' . $p->ID; }
function get_permalink(WP_Post $p): string { return 'http://example.test/post-' . $p->ID; }
function get_post_modified_time(string $f, bool $gmt, WP_Post $p): string { return '2026-08-12T10:00:00Z'; }
function get_post_meta(int $id, string $k, bool $single = false): mixed
{
    $s =& vp_store();
    return $s['meta'][$id][$k] ?? null;
}
function update_post_meta(int $id, string $k, mixed $v): void
{
    $s =& vp_store();
    $s['meta'][$id][$k] = $v;
}
function get_page_by_path(string $path, string $output = OBJECT, array $types = []): ?WP_Post
{
    $s =& vp_store();
    foreach ($s['posts'] as $p) {
        if ($p->post_name === $path) return $p;
    }
    return null;
}

/* ============================ scenario ============================ */

require $dist;

$failures = [];
function ok(bool $cond, string $name): void
{
    global $failures;
    if (! $cond) { $failures[] = $name; }
}

$secret = 'sk_rm_' . bin2hex(random_bytes(8));
update_option('vision_prime_connector', [
    'platform_url' => 'https://app.example.test',
    'site_id' => 9,
    'secret' => VP_Secret::encrypt($secret),
]);

$post = new WP_Post();
$post->ID = 7;
$post->post_name = 'rank-math-page';
$post->post_content = '<h2>Hello</h2><p>Body</p>';
vp_store()['posts'][] = $post;
update_post_meta(7, 'rank_math_title', 'Old RM Title');
update_post_meta(7, '_yoast_wpseo_title', 'Old Yoast Title');
update_post_meta(7, 'rank_math_description', 'Old RM Desc');
update_post_meta(7, '_yoast_wpseo_metadesc', 'Old Yoast Desc');

// Sign and dispatch update_meta_title.
$body = json_encode(['idempotency_key' => 'rm-cmd-1', 'site_id' => 9, 'type' => 'update_meta_title', 'payload' => ['post_id' => 7, 'title' => 'Rank Math Wins']]);
$ts = (string) time();
$nonce = 'rm-nonce';
$payload = "POST\n/vision-prime/v1/commands\n{$ts}\n{$nonce}\n" . hash('sha256', $body);
$sig = hash_hmac('sha256', $payload, $secret);
$req = new WP_REST_Request('POST', '/vision-prime/v1/commands', $body, [
    'x-vp-timestamp' => $ts,
    'x-vp-nonce' => $nonce,
    'x-vp-signature' => $sig,
], json_decode($body, true));

$connector = new Vision_Prime_Connector();
$resp = $connector->commands($req);
ok(($resp->data['status'] ?? '') === 'ack', 'commands endpoint acks under ' . $engine);
ok(get_post_meta(7, 'rank_math_title', true) === 'Rank Math Wins', 'rank_math_title was written');
if ($engine === 'both') {
    ok(get_post_meta(7, '_yoast_wpseo_title', true) === 'Rank Math Wins', 'yoast title also written when both active');
} else {
    ok(get_post_meta(7, '_yoast_wpseo_title', true) === 'Old Yoast Title', 'yoast title untouched when only rank math active');
}

// Content endpoint must prefer Rank Math values when both are set.
$contentReq = new WP_REST_Request('GET', '/vision-prime/v1/content', '', [], ['page' => 1]);
$content = $connector->content($contentReq);
$item = $content->data['data'][0] ?? [];
ok(($item['meta_title'] ?? '') === 'Rank Math Wins', 'content endpoint reads rank_math title first');
ok(($item['meta_description'] ?? '') === 'Old RM Desc', 'content endpoint reads rank_math description first');

echo empty($failures) ? "OK\n" : "FAIL: " . implode(' | ', $failures) . "\n";
exit(empty($failures) ? 0 : 1);
