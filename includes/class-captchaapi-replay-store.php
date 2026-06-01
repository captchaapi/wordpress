<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Makes every attestation single-use. A valid HMAC and a future expiry prove an
 * attestation is genuine and fresh, but not that it has not already been
 * submitted. Claiming the jti once, atomically, closes the replay window.
 *
 * Two backends, picked at runtime:
 *
 *   - A persistent object cache (Redis, Memcached) when one is installed.
 *     wp_cache_add() maps to an atomic add on the mainstream Redis and
 *     Memcached drop-ins and expires the key on its own. Atomicity here is a
 *     property of the active drop-in, not a guarantee WordPress itself makes.
 *   - A dedicated table otherwise. The primary key on jti makes INSERT IGNORE
 *     atomic regardless of backend, and a scheduled task sweeps expired rows.
 */
class Captchaapi_Replay_Store
{
    const TABLE = 'captchaapi_used_attestations';

    const CACHE_GROUP = 'captchaapi_jti';

    const PURGE_HOOK = 'captchaapi_purge_expired';

    /**
     * Claims a jti for the remaining lifetime of the attestation. Returns true on
     * the first claim, false if it was already used or on any error. Failing
     * closed means a storage hiccup rejects the submission rather than waving a
     * possible replay through.
     */
    public function claim(string $jti, int $ttl): bool
    {
        if ($jti === '' || $ttl <= 0) {
            return false;
        }

        if (wp_using_ext_object_cache()) {
            return (bool) wp_cache_add($jti, 1, self::CACHE_GROUP, $ttl);
        }

        return $this->claim_in_table($jti, $ttl);
    }

    private function claim_in_table(string $jti, int $ttl): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        // INSERT IGNORE returns 1 when the row is new and 0 when the primary key
        // already exists, so a duplicate jti (a replay) is rejected atomically.
        $inserted = $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$table} (jti, expires_at) VALUES (%s, %d)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $jti,
                time() + $ttl
            )
        );

        return $inserted === 1;
    }

    public function purge_expired(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE expires_at < %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                time()
            )
        );
    }

    public static function create_table(): void
    {
        global $wpdb;

        $table           = $wpdb->prefix . self::TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            jti varchar(64) NOT NULL,
            expires_at int unsigned NOT NULL,
            PRIMARY KEY  (jti),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function drop_table(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;
        $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
    }
}
