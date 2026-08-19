<?php

defined('ABSPATH') || exit;

/**
 * Integrity guard for the Vision Prime Connector.
 *
 * The shipped distribution is a single encoded file. At build time the SHA-256
 * of the file (with the SELF_HASH constant zeroed) is embedded into SELF_HASH.
 * At runtime we recompute the same value and compare — if the file was modified
 * in any way, the guard reports tampered and every sensitive operation refuses
 * to run, and the platform is told about it via the integrity fields on every
 * signed request.
 */
final class VP_Guard {
    /** Filled at build time with the self-referential SHA-256 of this file. */
    public const SELF_HASH = '';

    public static function current_file(): string {
        return __FILE__;
    }

    /** Stable fingerprint of this build, reported to the platform on every signed request. */
    public static function file_hash(): string {
        $raw = (string) file_get_contents(self::current_file());

        return hash('sha256', $raw);
    }

    /**
     * True when the file on disk does not match the build-time fingerprint.
     * Works by zeroing the embedded SELF_HASH before hashing (the value is
     * excluded from its own fingerprint, like a Git blob hash).
     */
    public static function tampered(): bool {
        if (self::SELF_HASH === '') {
            return false; // source tree (dev) — the encoded distribution carries the fingerprint
        }

        $raw = (string) file_get_contents(self::current_file());
        $normalized = preg_replace(
            "/const SELF_HASH = '[0-9a-f]{64}'/",
            "const SELF_HASH = '" . str_repeat('0', 64) . "'",
            $raw
        );
        $actual = hash('sha256', (string) $normalized);

        return ! hash_equals(self::SELF_HASH, $actual);
    }

    public static function is_tampered_flag(): int {
        return self::tampered() ? 1 : 0;
    }
}
