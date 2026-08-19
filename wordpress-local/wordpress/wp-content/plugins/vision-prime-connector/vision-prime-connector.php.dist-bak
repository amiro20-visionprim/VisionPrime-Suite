<?php
/**
 * Plugin Name: Vision Prime Connector
 * Description: Secure connection between WordPress and Vision Prime.
 * Version: 1.2.0
 * Requires PHP: 8.2
 */

defined('ABSPATH') || exit;

define('VISION_PRIME_CONNECTOR_VERSION', '1.2.0');
define('VISION_PRIME_OPTION', 'vision_prime_connector');

require_once __DIR__ . '/includes/class-vp-guard.php';
require_once __DIR__ . '/includes/class-vp-secret.php';
require_once __DIR__ . '/includes/class-vp-api-client.php';
require_once __DIR__ . '/includes/class-vp-request-verifier.php';

final class Vision_Prime_Connector {
    private const OPTION = VISION_PRIME_OPTION;

    public function __construct() {
        add_action('admin_menu', [$this, 'settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_vision_prime_pair', [$this, 'pair']);
        add_action('admin_post_vision_prime_health', [$this, 'health_check']);
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_settings(): void {
        register_setting('vision-prime', self::OPTION, ['sanitize_callback' => [$this, 'sanitize_settings']]);
    }

    public function sanitize_settings(array $value): array {
        return ['platform_url' => esc_url_raw($value['platform_url'] ?? ''), 'site_id' => absint($value['site_id'] ?? 0), 'secret' => sanitize_text_field($value['secret'] ?? '')];
    }

    public function settings_page(): void {
        // Top-level menu with the brand icon so the plugin is visible in the
        // admin sidebar (WP 7 no longer renders icons for self-hosted plugins
        // anywhere else — not in the plugins list, not in the details modal).
        add_menu_page('Vision Prime', 'Vision Prime', 'manage_options', 'vision-prime', [$this, 'render_settings'], self::menu_icon(), 3);
    }

    private static function menu_icon(): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><defs><linearGradient id="vp" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4338CA"/><stop offset="1" stop-color="#8B5CF6"/></linearGradient></defs><rect width="20" height="20" rx="4" fill="url(#vp)"/><text x="10" y="13.5" font-family="Arial,sans-serif" font-size="9" font-weight="bold" fill="#ffffff" text-anchor="middle">VP</text></svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function render_settings(): void {
        if (! current_user_can('manage_options')) return;
        $settings = get_option(self::OPTION, []); ?>
        <div class="wrap"><h1>Vision Prime Connector</h1><?php if ($notice = get_transient('vision_prime_notice')) { delete_transient('vision_prime_notice'); echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>'; } ?><form method="post" action="options.php"><?php settings_fields('vision-prime'); ?><p><label>Platform URL <input class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[platform_url]" value="<?php echo esc_attr($settings['platform_url'] ?? ''); ?>"></label></p><p><label>Site ID <input name="<?php echo esc_attr(self::OPTION); ?>[site_id]" value="<?php echo esc_attr($settings['site_id'] ?? ''); ?>"></label></p><?php submit_button('Save connection settings'); ?></form><hr><h2>Pair site</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="vision_prime_pair"><?php wp_nonce_field('vision_prime_pair'); ?><p><label>Pairing Token <input class="regular-text" type="password" name="pairing_token" autocomplete="off"></label></p><?php submit_button('Pair with Vision Prime'); ?></form><p><strong>Status:</strong> <?php echo empty($settings['secret']) ? 'Not connected' : 'Connected'; ?><?php if (VP_Guard::tampered()) { echo ' <strong style="color:#b32d2e;"> — integrity check FAILED</strong>'; } ?></p><?php if (! empty($settings['secret'])) { ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="vision_prime_health"><?php wp_nonce_field('vision_prime_health'); submit_button('Run signed health check', 'secondary', 'submit', false); ?></form><?php } ?></div><?php
    }

    public function pair(): void {
        if (! current_user_can('manage_options')) wp_die('Unauthorized.');
        check_admin_referer('vision_prime_pair');
        $settings = get_option(self::OPTION, []);
        $token = sanitize_text_field($_POST['pairing_token'] ?? '');
        $result = VP_API_Client::pair($settings, $token);
        if (is_wp_error($result)) { set_transient('vision_prime_notice', $result->get_error_message(), 60); }
        else { $settings['secret'] = VP_Secret::encrypt($result['secret']); update_option(self::OPTION, $settings, false); set_transient('vision_prime_notice', 'Connected successfully.', 60); }
        wp_safe_redirect(admin_url('admin.php?page=vision-prime'));
        exit;
    }

    public function health_check(): void {
        if (! current_user_can('manage_options')) wp_die('Unauthorized.');
        check_admin_referer('vision_prime_health');
        $settings = VP_Secret::unlock(get_option(self::OPTION, []));
        $result = empty($settings['secret']) ? new WP_Error('vision_prime_not_connected', 'Site is not paired.') : VP_API_Client::signed_health($settings);
        $message = is_wp_error($result) ? $result->get_error_message() : (wp_remote_retrieve_response_code($result) === 200 ? 'Health check succeeded.' : 'Health check failed.');
        set_transient('vision_prime_notice', $message, 60);
        wp_safe_redirect(admin_url('admin.php?page=vision-prime'));
        exit;
    }

    public function register_routes(): void {
        register_rest_route('vision-prime/v1', '/health', ['methods' => 'GET', 'callback' => [$this, 'health'], 'permission_callback' => '__return_true']);
        register_rest_route('vision-prime/v1', '/content', ['methods' => 'GET', 'callback' => [$this, 'content'], 'permission_callback' => [VP_Request_Verifier::class, 'verify']]);
        register_rest_route('vision-prime/v1', '/commands', ['methods' => 'POST', 'callback' => [$this, 'commands'], 'permission_callback' => [VP_Request_Verifier::class, 'verify']]);
        register_rest_route('vision-prime/v1', '/rollback', ['methods' => 'POST', 'callback' => [$this, 'rollback'], 'permission_callback' => [VP_Request_Verifier::class, 'verify']]);
        register_rest_route('vision-prime/v1', '/product-info', ['methods' => 'POST', 'callback' => [$this, 'product_info'], 'permission_callback' => [VP_Request_Verifier::class, 'verify']]);
    }

    public function health(): WP_REST_Response {
        return new WP_REST_Response(['plugin_version' => VISION_PRIME_CONNECTOR_VERSION, 'wordpress_version' => get_bloginfo('version'), 'php_version' => PHP_VERSION, 'rest_api' => true, 'integrity_hash' => VP_Guard::file_hash(), 'tampered' => VP_Guard::is_tampered_flag()]);
    }

    public function content(WP_REST_Request $request): WP_REST_Response {
        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = min(100, max(1, absint($request->get_param('per_page') ?: 50)));
        $query = new WP_Query(['post_type' => ['post','page'], 'post_status' => ['publish','draft','private'], 'posts_per_page' => $per_page, 'paged' => $page, 'orderby' => 'modified', 'order' => 'DESC']);
        $items = array_map(function (WP_Post $post): array {
            $content = (string) $post->post_content;
            preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $content, $matches);
            $headings = array_map('wp_strip_all_tags', $matches[1] ?? []);
            return ['id' => $post->ID, 'title' => get_the_title($post), 'url' => get_permalink($post), 'slug' => $post->post_name, 'type' => $post->post_type, 'status' => $post->post_status, 'modified_at' => get_post_modified_time('c', true, $post), 'meta_title' => self::read_meta($post->ID, 'title'), 'meta_description' => self::read_meta($post->ID, 'description'), 'headings' => $headings, 'word_count' => str_word_count(wp_strip_all_tags($content)), 'content_hash' => hash('sha256', $content), 'content' => $content];
        }, $query->posts);
        return new WP_REST_Response(['data' => $items, 'page' => $page, 'per_page' => $per_page, 'total' => (int) $query->found_posts, 'total_pages' => (int) $query->max_num_pages]);
    }

    /**
     * Execute a command dispatched by the Vision Prime platform.
     *
     * The platform signs the request (HMAC + timestamp + nonce) and waits for
     * this endpoint's response, so we must NEVER block on the result callback.
     * The result is reported back asynchronously to /connector/command-result.
     */
    public function commands(WP_REST_Request $request): WP_REST_Response {
        if (VP_Guard::tampered()) {
            return new WP_REST_Response(['error' => 'integrity check failed'], 403);
        }
        $params = $request->get_json_params();
        $settings = VP_Secret::unlock(get_option(self::OPTION, []));
        $idempotency_key = sanitize_key((string) ($params['idempotency_key'] ?? ''));
        $type = sanitize_key((string) ($params['type'] ?? ''));
        $payload = is_array($params['payload'] ?? null) ? $params['payload'] : [];

        // Never execute the same command twice.
        $dedupe_key = 'vision_prime_cmd_' . md5($idempotency_key);
        if ($idempotency_key !== '' && get_transient($dedupe_key)) {
            return new WP_REST_Response(['status' => 'ack', 'deduplicated' => true], 200);
        }

        try {
            $result = $this->execute_command($type, $payload);
            $status = 'executed';
        } catch (Throwable $e) {
            $result = ['error' => $e->getMessage()];
            $status = 'failed';
        }

        if ($idempotency_key !== '') {
            set_transient($dedupe_key, '1', DAY_IN_SECONDS);
        }

        VP_API_Client::send_command_result($settings, [
            'site_id' => (int) $settings['site_id'],
            'idempotency_key' => $idempotency_key,
            'status' => $status,
            'result' => $status === 'executed' ? $result : null,
            'error' => $status === 'failed' ? ($result['error'] ?? 'unknown error') : null,
        ]);

        return new WP_REST_Response(['status' => 'ack'], 200);
    }

    /**
     * Restore a previously executed change from its pre-change snapshot (D-013 auto rollback).
     *
     * Payload: { command_id, type, previous, post_id, idempotency_key }
     * The `previous` value comes from the encrypted rollback_snapshots stored server-side.
     * The result is reported back asynchronously with status=rolled_back so the platform
     * can mark the command and measure the rollback in impact_events.
     */
    public function rollback(WP_REST_Request $request): WP_REST_Response {
        if (VP_Guard::tampered()) {
            return new WP_REST_Response(['error' => 'integrity check failed'], 403);
        }
        $params = $request->get_json_params();
        $settings = VP_Secret::unlock(get_option(self::OPTION, []));
        $idempotency_key = sanitize_key((string) ($params['idempotency_key'] ?? ''));
        $dedupe_key = 'vision_prime_rb_' . md5($idempotency_key);
        if ($idempotency_key !== '' && get_transient($dedupe_key)) {
            return new WP_REST_Response(['status' => 'ack', 'deduplicated' => true], 200);
        }

        try {
            // The platform sends post_id at the TOP level of the rollback payload;
            // restore_command also accepts it nested inside `previous` for compatibility.
            $result = $this->restore_command((string) ($params['type'] ?? ''), is_array($params['previous'] ?? null) ? $params['previous'] : [], absint($params['post_id'] ?? 0));
            $status = 'rolled_back';
        } catch (Throwable $e) {
            $result = ['error' => $e->getMessage()];
            $status = 'failed';
        }

        if ($idempotency_key !== '') {
            set_transient($dedupe_key, '1', DAY_IN_SECONDS);
        }

        VP_API_Client::send_command_result($settings, [
            'site_id' => (int) $settings['site_id'],
            'idempotency_key' => $idempotency_key,
            'status' => $status,
            'result' => $status === 'rolled_back' ? $result : null,
            'error' => $status === 'failed' ? ($result['error'] ?? 'unknown error') : null,
        ]);

        // Rollback is synchronous on the platform side: it needs the actual restore
        // outcome here (not only the async command-result) so it can mark the command
        // rolled_back only when WordPress confirms the mutation was really undone.
        return new WP_REST_Response(['status' => 'ack', 'restored' => $status === 'rolled_back', 'result' => $result], 200);
    }

    /**
     * Restore the pre-change values of an executed command from its snapshot.
     *
     * @return array<string,mixed>
     * @throws RuntimeException When the target post is missing or the type is unknown.
     */
    private function restore_command(string $type, array $previous, int $fallback_post_id = 0): array {
        $post_id = absint($previous['post_id'] ?? 0);
        if ($post_id === 0) {
            $post_id = $fallback_post_id;
        }
        if ($post_id === 0) {
            throw new RuntimeException('Rollback payload has no valid post_id.');
        }
        if (get_post($post_id) === null) {
            throw new RuntimeException('Target post does not exist: ' . $post_id);
        }
        switch ($type) {
            case 'update_meta_title':
                $keys = self::meta_keys('title');
                $restored = sanitize_text_field((string) ($previous['title'] ?? ''));
                foreach ($keys as $key) { update_post_meta($post_id, $key, $restored); }
                return ['post_id' => $post_id, 'restored' => true, 'meta_keys' => $keys];
            case 'update_meta_description':
                $keys = self::meta_keys('description');
                $restored = sanitize_text_field((string) ($previous['description'] ?? ''));
                foreach ($keys as $key) { update_post_meta($post_id, $key, $restored); }
                return ['post_id' => $post_id, 'restored' => true, 'meta_keys' => $keys];
            case 'update_content':
            case 'update_product_description':
                $content = (string) ($previous['content'] ?? '');
                $updated = wp_update_post(['ID' => $post_id, 'post_content' => wp_kses_post($content)], true);
                if (is_wp_error($updated) || (int) $updated === 0) {
                    throw new RuntimeException('Failed to restore content for post ' . $post_id);
                }
                return ['post_id' => $post_id, 'restored' => true, 'content_length' => mb_strlen($content)];
            case 'update_product_title':
                $restored = sanitize_text_field((string) ($previous['title'] ?? ''));
                $updated = wp_update_post(['ID' => $post_id, 'post_title' => $restored], true);
                if (is_wp_error($updated) || (int) $updated === 0) {
                    throw new RuntimeException('Failed to restore product title for post ' . $post_id);
                }
                return ['post_id' => $post_id, 'restored' => true];
            case 'publish_new_article':
                // بازگشت مقالهٔ جدید = حذف پست ساخته‌شده (فقط در صورتی که واقعاً توسط VP ساخته شده باشد).
                $created_flag = (string) get_post_meta($post_id, '_vp_created_by', true);
                if ($created_flag !== 'vision-prime') {
                    throw new RuntimeException('Refusing to delete post ' . $post_id . ': not created by Vision Prime.');
                }
                $deleted = wp_delete_post($post_id, true);
                if ($deleted === false || $deleted === null) {
                    throw new RuntimeException('Failed to delete article post ' . $post_id);
                }
                return ['post_id' => $post_id, 'restored' => true, 'deleted' => true];
            default:
                throw new RuntimeException('Unknown command type for rollback: ' . $type);
        }
    }

    /**
     * @return array<string,mixed>
     * @throws RuntimeException When the target post is missing or the type is unknown.
     */
    private function execute_command(string $type, array $payload): array {
        $post_id = absint($payload['post_id'] ?? 0);
        if ($post_id === 0 && ! empty($payload['url'])) {
            $by_url = get_page_by_path($this->url_to_path((string) $payload['url']), OBJECT, ['post', 'page']);
            $post_id = $by_url instanceof WP_Post ? $by_url->ID : 0;
        }
        // publish_new_article پستِ جدید می‌سازد و به post_id/url از قبل موجود نیاز ندارد.
        if ($post_id === 0 && $type !== 'publish_new_article') {
            throw new RuntimeException('Command payload has no valid post_id or url target.');
        }
        switch ($type) {
            case 'update_meta_title':
                $keys = self::meta_keys('title');
                $previous = self::read_meta($post_id, 'title');
                $new = sanitize_text_field((string) ($payload['title'] ?? ''));
                foreach ($keys as $key) { update_post_meta($post_id, $key, $new); }
                return ['post_id' => $post_id, 'previous' => ['title' => $previous], 'new' => $new, 'meta_keys' => $keys];
            case 'update_meta_description':
                $keys = self::meta_keys('description');
                $previous = self::read_meta($post_id, 'description');
                $new = sanitize_text_field((string) ($payload['description'] ?? ''));
                foreach ($keys as $key) { update_post_meta($post_id, $key, $new); }
                return ['post_id' => $post_id, 'previous' => ['description' => $previous], 'new' => $new, 'meta_keys' => $keys];
            case 'update_content':
                $new = (string) ($payload['content'] ?? '');
                if ($new === '') {
                    throw new RuntimeException('Command payload has no content.');
                }
                $previous = get_post_field('post_content', $post_id);
                $updated = wp_update_post(['ID' => $post_id, 'post_content' => wp_kses_post($new)], true);
                if (is_wp_error($updated) || (int) $updated === 0) {
                    throw new RuntimeException('Failed to update content for post ' . $post_id);
                }
                // Full previous content is required for a lossless rollback snapshot (D-013).
                return ['post_id' => $post_id, 'previous' => ['content' => (string) $previous], 'new_length' => mb_strlen($new), 'updated' => (int) $updated];
            case 'update_product_title':
                $this->assert_product($post_id);
                $new = sanitize_text_field((string) ($payload['title'] ?? ''));
                if ($new === '') {
                    throw new RuntimeException('Command payload has no title.');
                }
                $previous = get_post_field('post_title', $post_id);
                $updated = wp_update_post(['ID' => $post_id, 'post_title' => $new], true);
                if (is_wp_error($updated) || (int) $updated === 0) {
                    throw new RuntimeException('Failed to update product title for post ' . $post_id);
                }
                // previous باید آرایه باشد تا با قرارداد rollback (RollbackCommand و restore_command) سازگار بماند.
                return ['post_id' => $post_id, 'previous' => ['title' => (string) $previous], 'new' => $new];
            case 'update_product_description':
                $this->assert_product($post_id);
                $new = (string) ($payload['description'] ?? '');
                if ($new === '') {
                    throw new RuntimeException('Command payload has no description.');
                }
                $previous = get_post_field('post_content', $post_id);
                $updated = wp_update_post(['ID' => $post_id, 'post_content' => wp_kses_post($new)], true);
                if (is_wp_error($updated) || (int) $updated === 0) {
                    throw new RuntimeException('Failed to update product description for post ' . $post_id);
                }
                // Full previous content is required for a lossless rollback snapshot (D-013).
                return ['post_id' => $post_id, 'previous' => ['content' => (string) $previous], 'new_length' => mb_strlen($new)];
            case 'publish_new_article':
                $title = sanitize_text_field((string) ($payload['title'] ?? ''));
                $content = (string) ($payload['content'] ?? '');
                if ($title === '' || $content === '') {
                    throw new RuntimeException('Command payload has no title or content.');
                }
                $slug = sanitize_title((string) ($payload['slug'] ?? ''));
                // پیش‌نویس محصول → پست ووکامرس (post_type=product)؛ مقاله → پست معمولی
                $content_type = (string) ($payload['content_type'] ?? 'article');
                $post_type = $content_type === 'product' ? 'product' : 'post';
                $post_id = wp_insert_post([
                    'post_type' => $post_type,
                    'post_status' => 'publish',
                    'post_title' => $title,
                    'post_content' => wp_kses_post($content),
                    'post_name' => $slug !== '' ? $slug : null,
                ], true);
                if (is_wp_error($post_id) || (int) $post_id === 0) {
                    throw new RuntimeException('Failed to create article post: ' . (is_wp_error($post_id) ? $post_id->get_error_message() : 'unknown'));
                }
                // علامت امنیت: پست‌هایی که VP ساخته — rollback فقط همین‌ها را حذف می‌کند.
                update_post_meta((int) $post_id, '_vp_created_by', 'vision-prime');
                // Meta SEO (title/description) را روی پست جدید می‌نویسیم تا همانجا دیده شود.
                $meta_title = sanitize_text_field((string) ($payload['meta_title'] ?? ''));
                if ($meta_title !== '') {
                    foreach (self::meta_keys('title') as $key) { update_post_meta((int) $post_id, $key, $meta_title); }
                }
                // اسکیمای پیشنهادی (Article/FAQPage) را به‌صورت JSON-LD در meta ذخیره می‌کنیم.
                $schema = is_array($payload['schema'] ?? null) ? $payload['schema'] : [];
                if ($schema !== []) {
                    update_post_meta((int) $post_id, '_vp_schema_jsonld', wp_json_encode($schema, JSON_UNESCAPED_UNICODE));
                }
                // Rollback برای مقاله/محصول جدید = حذف پست ساخته‌شده؛ snapshot فقط post_id را نگه می‌دارد.
                return ['post_id' => (int) $post_id, 'previous' => ['created' => true, 'post_id' => (int) $post_id, 'post_type' => $post_type], 'new_length' => mb_strlen($content), 'new_title' => $title];
            default:
                throw new RuntimeException('Unknown command type: ' . $type);
        }
    }

    /**
     * Return real WooCommerce price/stock for a product (or a plain post), so
     * reviewers can see the live values before approving a product publish.
     *
     * Payload: { post_id } or { slug } (WP post id or product slug).
     * Responds synchronously (no async callback) with the product data.
     */
    public function product_info(WP_REST_Request $request): WP_REST_Response {
        if (VP_Guard::tampered()) {
            return new WP_REST_Response(['error' => 'integrity check failed'], 403);
        }
        $params = $request->get_json_params();
        $post_id = absint($params['post_id'] ?? 0);
        $slug = sanitize_title((string) ($params['slug'] ?? ''));
        $post = $post_id > 0 ? get_post($post_id) : null;
        if ($post === null && $slug !== '') {
            $found = get_page_by_path($slug, OBJECT, ['product', 'post']);
            if ($found instanceof WP_Post) {
                $post = $found;
                $post_id = (int) $found->ID;
            }
        }
        if ($post === null) {
            return new WP_REST_Response(['error' => 'product_not_found'], 404);
        }

        $is_product = get_post_type($post_id) === 'product';
        $price = $regular_price = $sale_price = null;
        $stock_quantity = null;
        $stock_status = null;
        $currency = null;
        $in_stock = null;

        // WooCommerce product — real price/stock from the product object.
        if ($is_product && function_exists('wc_get_product')) {
            $product = wc_get_product($post_id);
            if ($product instanceof WC_Product) {
                $price = $product->get_price();
                $regular_price = $product->get_regular_price();
                $sale_price = $product->get_sale_price();
                $stock_quantity = $product->get_stock_quantity();
                $stock_status = $product->get_stock_status();
                $currency = get_woocommerce_currency();
                $in_stock = $product->is_in_stock();
            }
        } elseif ($is_product) {
            // No WooCommerce — fall back to raw post meta (still useful).
            $price = get_post_meta($post_id, '_price', true);
            $stock_quantity = get_post_meta($post_id, '_stock', true);
            $stock_status = get_post_meta($post_id, '_stock_status', true);
            $in_stock = $stock_status !== 'outofstock';
        }

        return new WP_REST_Response([
            'post_id' => $post_id,
            'title' => get_the_title($post_id),
            'post_type' => get_post_type($post_id),
            'url' => get_permalink($post_id),
            'is_product' => $is_product,
            'price' => $price !== null && $price !== '' ? (string) $price : null,
            'regular_price' => $regular_price !== null && $regular_price !== '' ? (string) $regular_price : null,
            'sale_price' => $sale_price !== null && $sale_price !== '' ? (string) $sale_price : null,
            'currency' => $currency,
            'stock_quantity' => $stock_quantity !== null && $stock_quantity !== '' ? (int) $stock_quantity : null,
            'stock_status' => $stock_status,
            'in_stock' => $in_stock,
        ]);
    }

    /**
     * Ensure the target is an existing product (WooCommerce) before a product mutation.
     */
    private function assert_product(int $post_id): void
    {
        if (get_post($post_id) === null) {
            throw new RuntimeException('Target post does not exist: ' . $post_id);
        }
        if (get_post_type($post_id) !== 'product') {
            throw new RuntimeException('Target post is not a product (type: ' . (get_post_type($post_id) ?: 'unknown') . ').');
        }
    }

    private static function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit) . '…';
    }

    private function url_to_path(string $url): string
    {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        return trim($path, '/');
    }

    /**
     * Which SEO engine(s) are installed and active on this site.
     *
     * Rank Math and Yoast store their meta under different keys; we write to
     * every engine that is actually active so the platform's recommendations
     * always land where the site owner can see them.
     */
    private static function seo_engine(): string
    {
        $rankMath = defined('RANK_MATH_VERSION') || class_exists('RankMath\\Helper');
        $yoast = defined('WPSEO_VERSION') || class_exists('WPSEO_Options');
        if ($rankMath && $yoast) { return 'both'; }
        if ($rankMath) { return 'rank_math'; }
        if ($yoast) { return 'yoast'; }
        return 'none';
    }

    /**
     * Meta keys to write for a field, according to the active SEO engine(s).
     * Falls back to the Yoast keys when no engine is detected (backwards
     * compatible with the original behaviour and bare WordPress installs).
     */
    private static function meta_keys(string $field): array
    {
        $rankMathKey = $field === 'title' ? 'rank_math_title' : 'rank_math_description';
        $yoastKey = $field === 'title' ? '_yoast_wpseo_title' : '_yoast_wpseo_metadesc';
        switch (self::seo_engine()) {
            case 'rank_math': return [$rankMathKey];
            case 'yoast': return [$yoastKey];
            case 'both': return [$rankMathKey, $yoastKey];
            default: return [$yoastKey];
        }
    }

    /**
     * Read the current meta value for a field, preferring Rank Math when set.
     */
    private static function read_meta(int $postId, string $field): string
    {
        $rankMathKey = $field === 'title' ? 'rank_math_title' : 'rank_math_description';
        $yoastKey = $field === 'title' ? '_yoast_wpseo_title' : '_yoast_wpseo_metadesc';
        $rankMath = get_post_meta($postId, $rankMathKey, true);
        if (is_string($rankMath) && $rankMath !== '') { return $rankMath; }
        $yoast = get_post_meta($postId, $yoastKey, true);
        return is_string($yoast) ? $yoast : '';
    }
}
new Vision_Prime_Connector();
