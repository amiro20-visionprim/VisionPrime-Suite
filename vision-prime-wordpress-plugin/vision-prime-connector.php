<?php
/**
 * Plugin Name: Vision Prime Connector
 * Description: Secure connection between WordPress and Vision Prime.
 * Version: 0.2.0
 * Requires PHP: 8.2
 */

defined('ABSPATH') || exit;

define('VISION_PRIME_CONNECTOR_VERSION', '0.2.0');

require_once __DIR__ . '/includes/class-vp-api-client.php';
require_once __DIR__ . '/includes/class-vp-request-verifier.php';

final class Vision_Prime_Connector {
    private const OPTION = 'vision_prime_connector';

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
        add_options_page('Vision Prime', 'Vision Prime', 'manage_options', 'vision-prime', [$this, 'render_settings']);
    }

    public function render_settings(): void {
        if (! current_user_can('manage_options')) return;
        $settings = get_option(self::OPTION, []); ?>
        <div class="wrap"><h1>Vision Prime Connector</h1><?php if ($notice = get_transient('vision_prime_notice')) { delete_transient('vision_prime_notice'); echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>'; } ?><form method="post" action="options.php"><?php settings_fields('vision-prime'); ?><p><label>Platform URL <input class="regular-text" name="<?php echo esc_attr(self::OPTION); ?>[platform_url]" value="<?php echo esc_attr($settings['platform_url'] ?? ''); ?>"></label></p><p><label>Site ID <input name="<?php echo esc_attr(self::OPTION); ?>[site_id]" value="<?php echo esc_attr($settings['site_id'] ?? ''); ?>"></label></p><?php submit_button('Save connection settings'); ?></form><hr><h2>Pair site</h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="vision_prime_pair"><?php wp_nonce_field('vision_prime_pair'); ?><p><label>Pairing Token <input class="regular-text" type="password" name="pairing_token" autocomplete="off"></label></p><?php submit_button('Pair with Vision Prime'); ?></form><p><strong>Status:</strong> <?php echo empty($settings['secret']) ? 'Not connected' : 'Connected'; ?></p><?php if (! empty($settings['secret'])) { ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="vision_prime_health"><?php wp_nonce_field('vision_prime_health'); submit_button('Run signed health check', 'secondary', 'submit', false); ?></form><?php } ?></div><?php
    }

    public function pair(): void {
        if (! current_user_can('manage_options')) wp_die('Unauthorized.');
        check_admin_referer('vision_prime_pair');
        $settings = get_option(self::OPTION, []);
        $token = sanitize_text_field($_POST['pairing_token'] ?? '');
        $result = VP_API_Client::pair($settings, $token);
        if (is_wp_error($result)) { set_transient('vision_prime_notice', $result->get_error_message(), 60); }
        else { $settings['secret'] = $result['secret']; update_option(self::OPTION, $settings, false); set_transient('vision_prime_notice', 'Connected successfully.', 60); }
        wp_safe_redirect(admin_url('options-general.php?page=vision-prime'));
        exit;
    }

    public function health_check(): void {
        if (! current_user_can('manage_options')) wp_die('Unauthorized.');
        check_admin_referer('vision_prime_health');
        $settings = get_option(self::OPTION, []);
        $result = empty($settings['secret']) ? new WP_Error('vision_prime_not_connected', 'Site is not paired.') : VP_API_Client::signed_health($settings);
        $message = is_wp_error($result) ? $result->get_error_message() : (wp_remote_retrieve_response_code($result) === 200 ? 'Health check succeeded.' : 'Health check failed.');
        set_transient('vision_prime_notice', $message, 60);
        wp_safe_redirect(admin_url('options-general.php?page=vision-prime'));
        exit;
    }

    public function register_routes(): void {
        register_rest_route('vision-prime/v1', '/health', ['methods' => 'GET', 'callback' => [$this, 'health'], 'permission_callback' => '__return_true']);
        register_rest_route('vision-prime/v1', '/content', ['methods' => 'GET', 'callback' => [$this, 'content'], 'permission_callback' => [VP_Request_Verifier::class, 'verify']]);
        register_rest_route('vision-prime/v1', '/commands', ['methods' => 'POST', 'callback' => [$this, 'commands'], 'permission_callback' => [VP_Request_Verifier::class, 'verify']]);
    }

    public function health(): WP_REST_Response {
        return new WP_REST_Response(['plugin_version' => VISION_PRIME_CONNECTOR_VERSION, 'wordpress_version' => get_bloginfo('version'), 'php_version' => PHP_VERSION, 'rest_api' => true]);
    }

    public function content(WP_REST_Request $request): WP_REST_Response {
        $page = max(1, absint($request->get_param('page') ?: 1));
        $per_page = min(100, max(1, absint($request->get_param('per_page') ?: 50)));
        $query = new WP_Query(['post_type' => ['post','page'], 'post_status' => ['publish','draft','private'], 'posts_per_page' => $per_page, 'paged' => $page, 'orderby' => 'modified', 'order' => 'DESC']);
        $items = array_map(function (WP_Post $post): array {
            $content = (string) $post->post_content;
            preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $content, $matches);
            $headings = array_map('wp_strip_all_tags', $matches[1] ?? []);
            return ['id' => $post->ID, 'title' => get_the_title($post), 'url' => get_permalink($post), 'slug' => $post->post_name, 'type' => $post->post_type, 'status' => $post->post_status, 'modified_at' => get_post_modified_time('c', true, $post), 'meta_title' => get_post_meta($post->ID, '_yoast_wpseo_title', true), 'meta_description' => get_post_meta($post->ID, '_yoast_wpseo_metadesc', true), 'headings' => $headings, 'word_count' => str_word_count(wp_strip_all_tags($content)), 'content_hash' => hash('sha256', $content), 'content' => $content];
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
        $params = $request->get_json_params();
        $settings = get_option(self::OPTION, []);
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
     * @return array<string,mixed>
     * @throws RuntimeException When the target post is missing or the type is unknown.
     */
    private function execute_command(string $type, array $payload): array {
        $post_id = absint($payload['post_id'] ?? 0);
        if ($post_id === 0 && ! empty($payload['url'])) {
            $by_url = get_page_by_path($this->url_to_path((string) $payload['url']), OBJECT, ['post', 'page']);
            $post_id = $by_url instanceof WP_Post ? $by_url->ID : 0;
        }
        if ($post_id === 0) {
            throw new RuntimeException('Command payload has no valid post_id or url target.');
        }
        switch ($type) {
            case 'update_meta_title':
                $previous = get_post_meta($post_id, '_yoast_wpseo_title', true);
                $new = sanitize_text_field((string) ($payload['title'] ?? ''));
                update_post_meta($post_id, '_yoast_wpseo_title', $new);
                return ['post_id' => $post_id, 'previous' => $previous, 'new' => $new];
            case 'update_meta_description':
                $previous = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                $new = sanitize_text_field((string) ($payload['description'] ?? ''));
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $new);
                return ['post_id' => $post_id, 'previous' => $previous, 'new' => $new];
            default:
                throw new RuntimeException('Unknown command type: ' . $type);
        }
    }

    private function url_to_path(string $url): string {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        return trim($path, '/');
    }
}
new Vision_Prime_Connector();
