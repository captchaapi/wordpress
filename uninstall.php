<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('captchaapi_options');
delete_option('captchaapi_version');
delete_option('captchaapi_last_service_state');
delete_option('captchaapi_connect_pending');
delete_transient('captchaapi_service_state');
delete_option('captchaapi_stats');
delete_transient('captchaapi_usage');

// Drop the legacy single-use store if an older version left it behind. The
// server owns single-use now, so there is no table or purge cron in 2.0.
$captchaapi_hook = 'captchaapi_purge_expired';
$captchaapi_timestamp = wp_next_scheduled($captchaapi_hook);
if ($captchaapi_timestamp) {
    wp_unschedule_event($captchaapi_timestamp, $captchaapi_hook);
}

global $wpdb;

$captchaapi_table = $wpdb->prefix . 'captchaapi_used_attestations';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is a fixed prefix plus a constant string, not user input
$wpdb->query("DROP TABLE IF EXISTS {$captchaapi_table}");
