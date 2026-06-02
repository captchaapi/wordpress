<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-captchaapi-replay-store.php';

delete_option('captchaapi_options');

Captchaapi_Replay_Store::drop_table();

$captchaapi_timestamp = wp_next_scheduled(Captchaapi_Replay_Store::PURGE_HOOK);
if ($captchaapi_timestamp) {
    wp_unschedule_event($captchaapi_timestamp, Captchaapi_Replay_Store::PURGE_HOOK);
}
