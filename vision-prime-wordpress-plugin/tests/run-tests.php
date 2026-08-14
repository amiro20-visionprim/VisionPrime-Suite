<?php

declare(strict_types=1);

/**
 * Vision Prime Connector — test harness.
 *
 * Loads the ENCODED distribution (dist/vision-prime-connector.php) inside a
 * stubbed WordPress environment and verifies:
 *   - the encoded file parses and boots,
 *   - the integrity guard detects tampering and refuses to sign,
 *   - the secret is encrypted at rest and legacy values migrate,
 *   - HMAC verification accepts valid requests and rejects bad/stale/replayed ones,
 *   - the commands endpoint executes meta updates and reports results back.
 *
 * Usage: php tests/run-tests.php   (run `php build.php` first)
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__);
$dist = $root . '/dist/vision-prime-connector.php';
if (! is_file($dist)) {
    fwrite(STDERR, "Missing {$dist} — run `php build.php` first.\n");
    exit(1);
}

/* ============================ WordPress stubs ============================ */

define('ABSPATH', __DIR__);
define('MINUTE_IN_SECONDS', 60);
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);

class WP_Error
{
    private string $code;
    private string $message;
    private mixed $data;

    public function __construct(string $code = '', string $message = '', mixed $data = '')
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
    public function get_error_data(): mixed { return $this->data; }
}

function &vp_store(): array
{
    static $s = ['options' => [], 'transients' => [], 'meta' => [], 'posts' => [], 'hooks' => [], 'routes' => [], 'http' => []];
    return $s;
}

function get_option(string $k, mixed $d = false): mixed
{
    $s =& vp_store();
    return array_key_exists($k, $s['options']) ? $s['options'][$k] : $d;
}
function update_option(string $k, mixed $v, bool $autoload = true): bool
{
    $s =& vp_store();
    $s['options'][$k] = $v;
    return true;
}
function get_transient(string $k): mixed
{
    $s =& vp_store();
    if (! isset($s['transients'][$k])) return false;
    [$v, $exp] = $s['transients'][$k];
    return $exp > time() ? $v : false;
}
function set_transient(string $k, mixed $v, int $exp): bool
{
    $s =& vp_store();
    $s['transients'][$k] = [$v, time() + $exp];
    return true;
}
function delete_transient(string $k): bool
{
    $s =& vp_store();
    unset($s['transients'][$k]);
    return true;
}

function add_action(string $hook, callable $cb): void { $s =& vp_store(); $s['hooks'][] = [$hook, $cb]; }
function add_options_page(): void {}
function add_menu_page(): void {}
function register_setting(): void {}
function register_rest_route(string $ns, string $route, array $args): void { $s =& vp_store(); $s['routes'][] = [$ns, $route, $args]; }
function settings_fields(): void {}
function submit_button(): void {}
function wp_nonce_field(): void {}
function current_user_can(): bool { return true; }
function wp_die(string $m): never { throw new RuntimeException($m); }
function check_admin_referer(): int { return 1; }
function admin_url(string $p = ''): string { return 'http://example.test/wp-admin/' . ltrim($p, '/'); }
function wp_safe_redirect(string $u): void { $s =& vp_store(); $s['redirect'] = $u; }
function wp_salt(string $scheme = 'auth'): string { return $GLOBALS['vp_salt'] ?? 'test-auth-salt-0123456789abcdef'; }
function wp_generate_uuid4(): string
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0x0fff) | 0x4000, random_int(0, 0x3fff) | 0x8000, random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff));
}
function get_bloginfo(string $f = ''): string { return '6.7'; }
function home_url(string $p = ''): string { return 'http://example.test' . $p; }
function esc_url_raw(string $u): string { return $u; }
function esc_url(string $u): string { return $u; }
function esc_attr(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
function esc_html(string $s): string { return htmlspecialchars($s); }
function sanitize_text_field(string $s): string { return trim($s); }
function sanitize_key(string $s): string { return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($s)); }
function absint(mixed $v): int { return abs((int) $v); }
function wp_strip_all_tags(string $s): string { return strip_tags($s); }

function wp_json_encode(mixed $v, int $options = 0, int $depth = 512): string|false
{
    return json_encode($v, $options, $depth);
}

function wp_remote_post(string $url, array $args = []): array
{
    $s =& vp_store();
    $s['http'][] = ['url' => $url, 'args' => $args];
    return ['headers' => [], 'body' => '{"ok":true}', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
}
function wp_remote_retrieve_response_code(array $r): int { return $r['response']['code'] ?? 200; }
function wp_remote_retrieve_body(array $r): string { return $r['body'] ?? ''; }
function is_wp_error(mixed $x): bool { return $x instanceof WP_Error; }

class WP_REST_Request
{
    private array $headers;
    private string $method;
    private string $route;
    private string $body;
    private array $params;

    public function __construct(string $method = 'GET', string $route = '', string $body = '', array $headers = [], array $params = [])
    {
        $this->method = $method;
        $this->route = $route;
        $this->body = $body;
        $this->headers = $headers;
        $this->params = $params;
    }

    public function get_header(string $k): ?string { return $this->headers[$k] ?? null; }
    public function get_method(): string { return $this->method; }
    public function get_route(): string { return $this->route; }
    public function get_body(): string { return $this->body; }
    public function get_param(string $k): mixed { return $this->params[$k] ?? null; }
    public function get_json_params(): array { return $this->params; }
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
        $this->max_num_pages = max(1, (int) ceil($this->found_posts / 10));
    }
}

function get_the_title(WP_Post $p): string { return 'Title ' . $p->ID; }
function get_permalink(WP_Post $p): string { return 'http://example.test/post-' . $p->ID; }
function get_post_modified_time(string $f, bool $gmt, WP_Post $p): string { return '2026-08-09T10:00:00Z'; }
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

/* ============================ test runner ============================ */

$GLOBALS['vp_tests'] = ['pass' => 0, 'fail' => 0, 'failures' => []];

function check(string $name, bool $cond, string $detail = ''): void
{
    if ($cond) {
        $GLOBALS['vp_tests']['pass']++;
        echo "  \xE2\x9C\x93 {$name}\n";
    } else {
        $GLOBALS['vp_tests']['fail']++;
        $GLOBALS['vp_tests']['failures'][] = "{$name}" . ($detail !== '' ? " — {$detail}" : '');
        echo "  \xC3\x97 {$name}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function reset_wp(): void
{
    $s =& vp_store();
    $s['options'] = [];
    $s['transients'] = [];
    $s['meta'] = [];
    $s['posts'] = [];
    $s['http'] = [];
    $GLOBALS['vp_salt'] = 'test-auth-salt-0123456789abcdef';
}

/* ============================ boot the encoded plugin ============================ */

echo "Loading encoded distribution: {$dist}\n";
reset_wp();
require $dist;

check('classes are defined', class_exists('Vision_Prime_Connector') && class_exists('VP_Guard') && class_exists('VP_Secret') && class_exists('VP_API_Client') && class_exists('VP_Request_Verifier'));

// fire the WP hooks the constructor registered, like WordPress would
foreach (vp_store()['hooks'] as [$hook, $cb]) {
    if (in_array($hook, ['rest_api_init', 'admin_init', 'admin_menu'], true)) {
        $cb();
    }
}
check('rest routes registered (health/content/commands)', isset(vp_store()['routes'][0]) && isset(vp_store()['routes'][1]) && isset(vp_store()['routes'][2]));

/* ============================ integrity guard ============================ */

check('pristine file is not tampered', VP_Guard::tampered() === false);
check('integrity hash is a 64-hex string', (bool) preg_match('/^[a-f0-9]{64}$/', VP_Guard::file_hash()));

$tamperedCopy = dirname($dist) . '/_tampered_test.php';
$src = file_get_contents($dist);
$pos = strpos($src, 'Version: 1.2.0');
$src[$pos + 9] = '9'; // flip a digit in the header — still valid PHP, but modified
file_put_contents($tamperedCopy, $src);
$runner = dirname($dist) . '/_tamper_runner.php';
file_put_contents($runner, "<?php\n"
    . "define('ABSPATH', __DIR__);\n"
    . "function add_action(){} function register_setting(){} function add_options_page(){} function add_menu_page(){} function register_rest_route(){}\n"
    . 'require ' . var_export($tamperedCopy, true) . ";\n"
    . "echo VP_Guard::tampered() ? 'T' : 'C';\n");
// fresh include of the modified copy in a separate process (no class redefinition)
$tamperedOk = trim((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' 2>&1'));
unlink($tamperedCopy);
unlink($runner);
check('modified file is detected as tampered', $tamperedOk === 'T', "got: {$tamperedOk}");

/* ============================ secret encryption ============================ */

$secret = 'sk_live_' . bin2hex(random_bytes(16));
$enc = VP_Secret::encrypt($secret);
check('encrypted secret is prefixed', str_starts_with($enc, 'vp1:'));
check('encrypted secret does not contain plaintext', ! str_contains($enc, $secret));
check('decrypt round-trip works', VP_Secret::decrypt($enc) === $secret);

reset_wp();
$legacy = ['platform_url' => 'https://app.example.test', 'site_id' => 7, 'secret' => 'legacy-plain-secret'];
$unlocked = VP_Secret::unlock($legacy);
check('legacy plaintext secret is decrypted for use', $unlocked['secret'] === 'legacy-plain-secret');
check('legacy secret migrated to encrypted storage', str_starts_with(get_option('vision_prime_connector')['secret'] ?? '', 'vp1:'));
check('stored value is not plaintext', (get_option('vision_prime_connector')['secret'] ?? '') !== 'legacy-plain-secret');

$GLOBALS['vp_salt'] = 'different-salt-for-another-site';
$wrongSalt = VP_Secret::decrypt($enc);
check('decrypt with wrong salt fails closed (empty)', $wrongSalt === '');

/* ============================ HMAC verification ============================ */

reset_wp();
$secret = 'sk_test_hmac_' . bin2hex(random_bytes(8));
update_option('vision_prime_connector', ['platform_url' => 'https://app.example.test', 'site_id' => 1, 'secret' => VP_Secret::encrypt($secret)]);

$body = json_encode(['site_id' => 1, 'post_id' => 42, 'type' => 'update_meta_title', 'payload' => ['title' => 'New Title']]);
$ts = (string) time();
$nonce = 'nonce-' . bin2hex(random_bytes(8));
$payload = "POST\n/vision-prime/v1/commands\n{$ts}\n{$nonce}\n" . hash('sha256', $body);
$sig = hash_hmac('sha256', $payload, $secret);

$valid = new WP_REST_Request('POST', '/vision-prime/v1/commands', $body, [
    'x-vp-timestamp' => $ts,
    'x-vp-nonce' => $nonce,
    'x-vp-signature' => $sig,
], ['site_id' => 1, 'post_id' => 42, 'type' => 'update_meta_title', 'payload' => ['title' => 'New Title']]);

check('valid signed request is accepted', VP_Request_Verifier::verify($valid) === true);

$bad = new WP_REST_Request('POST', '/vision-prime/v1/commands', $body, [
    'x-vp-timestamp' => $ts,
    'x-vp-nonce' => $nonce . '-x',
    'x-vp-signature' => str_repeat('0', 64),
], []);
$badResult = VP_Request_Verifier::verify($bad);
check('invalid signature is rejected', is_wp_error($badResult) && $badResult->get_error_code() === 'vision_prime_invalid_signature');

$stale = new WP_REST_Request('POST', '/vision-prime/v1/commands', $body, [
    'x-vp-timestamp' => (string) (time() - 3600),
    'x-vp-nonce' => 'nonce-stale',
    'x-vp-signature' => $sig,
], []);
$staleResult = VP_Request_Verifier::verify($stale);
check('stale timestamp is rejected', is_wp_error($staleResult) && $staleResult->get_error_code() === 'vision_prime_expired');

$replay = new WP_REST_Request('POST', '/vision-prime/v1/commands', $body, [
    'x-vp-timestamp' => $ts,
    'x-vp-nonce' => $nonce,
    'x-vp-signature' => $sig,
], []);
check('replay of a used nonce is rejected', is_wp_error(VP_Request_Verifier::verify($replay)) && VP_Request_Verifier::verify($replay)->get_error_code() === 'vision_prime_replay');

/* ============================ signed outbound requests ============================ */

$signed = VP_API_Client::signed_request(['secret' => $secret], 'POST', 'connector/health', ['site_id' => 1]);
check('signed request has all headers', ! is_wp_error($signed) && isset($signed['headers']['X-VP-Timestamp'], $signed['headers']['X-VP-Nonce'], $signed['headers']['X-VP-Signature']));
if (! is_wp_error($signed)) {
    $expectedSig = hash_hmac('sha256', 'POST' . "\n" . 'connector/health' . "\n" . $signed['headers']['X-VP-Timestamp'] . "\n" . $signed['headers']['X-VP-Nonce'] . "\n" . hash('sha256', $signed['body']), $secret);
    check('outbound signature matches independent HMAC', hash_equals($expectedSig, $signed['headers']['X-VP-Signature']));
}

/* ============================ commands endpoint flow ============================ */

reset_wp();
$secret = 'sk_cmd_' . bin2hex(random_bytes(8));
update_option('vision_prime_connector', ['platform_url' => 'https://app.example.test', 'site_id' => 5, 'secret' => VP_Secret::encrypt($secret)]);
$post = new WP_Post();
$post->ID = 42;
$post->post_name = 'sample-page';
$post->post_content = '<h1>Hello</h1><p>World</p>';
vp_store()['posts'][] = $post;
update_post_meta(42, '_yoast_wpseo_title', 'Old Title');

$body = json_encode(['idempotency_key' => 'cmd-abc-123', 'site_id' => 5, 'type' => 'update_meta_title', 'payload' => ['post_id' => 42, 'title' => 'Brand New Title']]);
$ts = (string) time();
$nonce = 'cmd-nonce';
$payload = "POST\n/vision-prime/v1/commands\n{$ts}\n{$nonce}\n" . hash('sha256', $body);
$sig = hash_hmac('sha256', $payload, $secret);

$req = new WP_REST_Request('POST', '/vision-prime/v1/commands', $body, [
    'x-vp-timestamp' => $ts,
    'x-vp-nonce' => $nonce,
    'x-vp-signature' => $sig,
], json_decode($body, true));

$connector = new Vision_Prime_Connector();
$response = $connector->commands($req);
check('command executes and acks', $response->status === 200 && ($response->data['status'] ?? '') === 'ack');
check('meta title was updated on the post', get_post_meta(42, '_yoast_wpseo_title', true) === 'Brand New Title');
check('result callback was sent to the platform', count(vp_store()['http']) === 1 && str_ends_with(vp_store()['http'][0]['url'], '/connector/command-result'));

$http = vp_store()['http'][0]['args'] ?? [];
check('result callback is signed', isset($http['headers']['X-VP-Signature']) && $http['headers']['X-VP-Signature'] !== '');
$sent = json_decode($http['body'] ?? '{}', true);
check('result callback carries integrity hash', isset($sent['integrity_hash']) && (bool) preg_match('/^[a-f0-9]{64}$/', (string) $sent['integrity_hash']));
check('result callback reports executed status', ($sent['status'] ?? '') === 'executed');

/* ============================ content endpoint ============================ */

$contentBody = json_encode([]);
$contentTs = (string) time();
$contentNonce = 'content-nonce';
$contentPayload = "GET\n/vision-prime/v1/content\n{$contentTs}\n{$contentNonce}\n" . hash('sha256', $contentBody);
$contentSig = hash_hmac('sha256', $contentPayload, $secret);
$contentReq = new WP_REST_Request('GET', '/vision-prime/v1/content', $contentBody, [
    'x-vp-timestamp' => $contentTs,
    'x-vp-nonce' => $contentNonce,
    'x-vp-signature' => $contentSig,
], ['page' => 1]);
$contentResponse = $connector->content($contentReq);
check('content endpoint returns synced posts', isset($contentResponse->data['data'][0]['id']) && $contentResponse->data['data'][0]['id'] === 42);
check('content includes content hash', isset($contentResponse->data['data'][0]['content_hash']) && strlen($contentResponse->data['data'][0]['content_hash']) === 64);

/* ============================ health endpoint ============================ */

$health = $connector->health();
check('health reports integrity hash', isset($health->data['integrity_hash']) && (bool) preg_match('/^[a-f0-9]{64}$/', (string) $health->data['integrity_hash']));
check('health reports tampered flag', ($health->data['tampered'] ?? null) === 0);

/* ============================ Rank Math / Yoast engines ============================ */

// Fresh process with RANK_MATH_VERSION defined (and optionally WPSEO_VERSION)
// so the plugin detects the SEO engine and routes meta writes accordingly.
$rmScenario = dirname(__DIR__) . '/tests/run-rankmath-scenario.php';
$rmCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($rmScenario) . ' ' . escapeshellarg($dist);

putenv('VP_ENGINE=rank_math');
$rmOut = trim((string) shell_exec($rmCmd . ' 2>&1'));
check('rank math active: meta writes to rank_math_* keys', $rmOut === 'OK', "got: {$rmOut}");

putenv('VP_ENGINE=both');
$bothOut = trim((string) shell_exec($rmCmd . ' 2>&1'));
check('rank math + yoast active: meta writes to both engines', $bothOut === 'OK', "got: {$bothOut}");

putenv('VP_ENGINE');

/* ============================ summary ============================ */

echo "\n" . str_repeat('=', 60) . "\n";
$t = $GLOBALS['vp_tests'];
echo "Tests: {$t['pass']} passed, {$t['fail']} failed\n";
if ($t['fail'] > 0) {
    echo "Failures:\n";
    foreach ($t['failures'] as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}
echo "ALL PLUGIN TESTS GREEN\n";
