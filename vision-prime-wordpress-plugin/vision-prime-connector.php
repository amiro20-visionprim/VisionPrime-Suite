<?php
/**
 * Plugin Name: Vision Prime Connector
 * Description: Secure connection between WordPress and Vision Prime.
 * Version: 0.1.0
 * Requires PHP: 8.2
 */

defined('ABSPATH') || exit;

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
    }

    public function health(): WP_REST_Response {
        return new WP_REST_Response(['plugin_version' => '0.1.0', 'wordpress_version' => get_bloginfo('version'), 'php_version' => PHP_VERSION, 'rest_api' => true]);
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
}
new Vision_Prime_Connector();
