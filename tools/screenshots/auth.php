<?php

/**
 * Prints an administrator's auth cookies as JSON, so the screenshot run can open
 * wp-admin without anyone's password and without touching one.
 *
 * Run through wp-cli: wp eval-file auth.php
 */

$user_id = (int) (getenv('CAPTCHAAPI_SHOT_USER') ?: 1);
$user    = get_user_by('id', $user_id);

if (! $user || ! user_can($user, 'manage_options')) {
    WP_CLI::error('User ' . $user_id . ' is not an administrator of this site.');
}

$expiration = time() + (3 * HOUR_IN_SECONDS);
$host       = COOKIE_DOMAIN ?: wp_parse_url(home_url(), PHP_URL_HOST);

echo wp_json_encode([
    'home'    => home_url(),
    'cookies' => [
        [
            'name'   => LOGGED_IN_COOKIE,
            'value'  => wp_generate_auth_cookie($user_id, $expiration, 'logged_in'),
            'domain' => $host,
            'path'   => COOKIEPATH ?: '/',
        ],
        [
            'name'   => SECURE_AUTH_COOKIE,
            'value'  => wp_generate_auth_cookie($user_id, $expiration, 'secure_auth'),
            'domain' => $host,
            'path'   => ADMIN_COOKIE_PATH,
        ],
    ],
]);
