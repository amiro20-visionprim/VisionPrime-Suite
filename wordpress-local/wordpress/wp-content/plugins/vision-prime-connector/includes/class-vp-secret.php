<?php

defined('ABSPATH') || exit;

/**
 * Secret protection for the Vision Prime Connector.
 *
 * The pairing secret is stored in wp_options. A plaintext secret there would be
 * exposed by any database leak or backup. We store it AES-256-GCM encrypted with
 * a key derived from the site's AUTH_KEY salt (wp_salt), so reading the database
 * alone is not enough to recover the secret — the attacker would also need file
 * access to wp-config.php.
 */
final class VP_Secret {
    private const PREFIX = 'vp1:';
    private const OPTION = 'vision_prime_connector';

    public static function encrypt(string $plain): string {
        if (! function_exists('openssl_encrypt') || $plain === '') {
            return $plain; // degrade gracefully when openssl is unavailable
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            return $plain;
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): string {
        if (! str_starts_with($stored, self::PREFIX)) {
            return $stored; // legacy plaintext
        }
        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 12 + 16) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 12 + 16);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? '' : $plain;
    }

    /**
     * Return settings with a usable (decrypted) secret, migrating a legacy
     * plaintext secret to encrypted storage on first use.
     *
     * @param array $settings Raw settings as stored in wp_options.
     * @return array Settings with the secret decrypted in place.
     */
    public static function unlock(array $settings): array {
        if (empty($settings['secret'])) {
            return $settings;
        }
        $stored = (string) $settings['secret'];
        if (! str_starts_with($stored, self::PREFIX)) {
            $settings['secret'] = self::encrypt($stored);
            update_option(self::OPTION, $settings, false); // persist migrated (encrypted) form
        }
        $settings['secret'] = self::decrypt((string) $settings['secret']);

        return $settings;
    }

    private static function key(): string {
        $salt = function_exists('wp_salt') ? (string) wp_salt('auth') : '';
        if ($salt === '') {
            $salt = defined('AUTH_KEY') ? (string) AUTH_KEY : '';
        }

        return hash('sha256', 'vision-prime-connector-v1|' . $salt, true); // 32 bytes
    }
}
