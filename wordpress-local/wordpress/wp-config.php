<?php
/**
 * Vision Prime - Local WordPress Test Instance
 * Uses SQLite via db.php drop-in
 */

define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', '' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

/**
 * Authentication unique keys and salts.
 */
define( 'AUTH_KEY',         'vp-test-auth-key-!@#$%^&*()_+-=[]{}|;:,.<>?~`' );
define( 'SECURE_AUTH_KEY',  'vp-test-secure-auth-key-!@#$%^&*()_+-=[]{}|;:,.<>?~`' );
define( 'LOGGED_IN_KEY',    'vp-test-logged-in-key-!@#$%^&*()_+-=[]{}|;:,.<>?~`' );
define( 'NONCE_KEY',        'vp-test-nonce-key-!@#$%^&*()_+-=[]{}|;:,.<>?~`' );
define( 'AUTH_SALT',        'vp-test-auth-salt-!@#$%^&*()_+-=[]{}|;:,.<>?~`' );
define( 'SECURE_AUTH_SALT', 'vp-test-secure-auth-salt-!@#$%^&*()_+-=[]{}|;:,.<>?~`' );
define( 'LOGGED_IN_SALT',   'vp-test-logged-in-salt-!@#$%^&*()_+-=[]{}|;:,.<>?~`' );
define( 'NONCE_SALT',       'vp-test-nonce-salt-!@#$%^&*()_+-=[]{}|;:,.<>?~`' );

/**
 * WordPress database table prefix.
 */
$table_prefix = 'wp_';

/**
 * Debug mode for local development.
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

/**
 * Disable file editing for security.
 */
define( 'DISALLOW_FILE_EDIT', false );

/**
 * Set custom content directory for plugin uploads.
 */
define( 'WP_CONTENT_DIR', __DIR__ . '/wp-content' );
define( 'WP_CONTENT_URL', 'http://localhost:8080/wp-content' );

/**
 * Absolute path to the WordPress directory.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/**
 * Sets up WordPress vars and included files.
 */
require_once ABSPATH . 'wp-settings.php';
