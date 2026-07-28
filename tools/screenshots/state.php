<?php

/**
 * Puts the test site into the state a screenshot needs, and puts it back.
 *
 * The screens worth showing are the ones a fresh test install never reaches: an
 * Activity panel with a month of traffic behind it, and an account the service
 * has stopped issuing challenges for. Both are options, so both can be staged.
 *
 * Run through wp-cli: wp eval-file state.php <backup|healthy|blocked|restore>
 *
 * Everything it overwrites is saved first, and restore puts all of it back -
 * including the site language, which is forced to English because the shots go
 * to a directory listing read in English.
 */

$mode = isset($args[0]) ? (string) $args[0] : '';

$backup_file = get_temp_dir() . 'captchaapi-screenshot-state.json';

$keys = [
    'captchaapi_options',
    'captchaapi_stats',
    'captchaapi_last_service_state',
    'WPLANG',
];

switch ($mode) {
    case 'backup':
        $backup = [];

        foreach ($keys as $key) {
            $backup[$key] = get_option($key, null);
        }

        file_put_contents($backup_file, wp_json_encode($backup));
        update_option('WPLANG', '');

        WP_CLI::success('Saved the current state to ' . $backup_file . ' and switched the admin to English.');
        break;

    case 'healthy':
        delete_option('captchaapi_last_service_state');
        set_transient('captchaapi_service_state', ['state' => 'enforcing', 'code' => ''], 5 * MINUTE_IN_SECONDS);

        // The seven-day figures have to come out below the thirty-day ones, or
        // the settings screen prints the same number twice and the shot says
        // nothing about the longer window.
        $recent_passed  = [38, 71, 54, 63, 49, 82, 55];
        $recent_blocked = [11, 24, 17, 22, 15, 31, 17];

        $stats = [];

        foreach ($recent_passed as $offset => $passed) {
            $stats[gmdate('Ymd', time() - ($offset * DAY_IN_SECONDS))] = [
                'passed'  => $passed,
                'blocked' => $recent_blocked[$offset],
            ];
        }

        $older_days   = 23;
        $passed_left  = 1786 - array_sum($recent_passed);
        $blocked_left = 602 - array_sum($recent_blocked);

        for ($i = 0; $i < $older_days; $i++) {
            $day  = gmdate('Ymd', time() - ((7 + $i) * DAY_IN_SECONDS));
            $last = $i === $older_days - 1;

            $passed  = $last ? $passed_left : min(45 + (($i * 17) % 33), $passed_left - ($older_days - 1 - $i));
            $blocked = $last ? $blocked_left : min(12 + (($i * 11) % 19), $blocked_left - ($older_days - 1 - $i));

            $passed_left  -= $passed;
            $blocked_left -= $blocked;
            $stats[$day]   = ['passed' => $passed, 'blocked' => $blocked];
        }

        $stats['last'] = time() - (4 * MINUTE_IN_SECONDS);

        update_option('captchaapi_stats', $stats, false);

        // What the service would have answered. Staged rather than fetched: the
        // shot must not depend on a dev account's real allowance.
        set_transient('captchaapi_usage', [
            'period_start' => gmdate('Y-m-01'),
            'challenges'   => 2640,
            'verified'     => 1786,
            'used'         => 2640,
            'limit'        => 5000,
            'tier'         => 'free',
            'stale_after'  => 900,
        ], 12 * HOUR_IN_SECONDS);

        WP_CLI::success('Staged a month of traffic and an account at 2,640 of 5,000.');
        break;

    case 'blocked':
        $problem = ['state' => 'not_enforceable', 'code' => 'free_tier_limit_reached'];

        update_option('captchaapi_last_service_state', $problem + ['time' => time() - 600], false);
        set_transient('captchaapi_service_state', $problem, 5 * MINUTE_IN_SECONDS);

        WP_CLI::success('Staged an account over its free tier limit.');
        break;

    case 'restore':
        if (! file_exists($backup_file)) {
            WP_CLI::error('No saved state at ' . $backup_file . '. Nothing to restore.');
        }

        $backup = json_decode((string) file_get_contents($backup_file), true);

        foreach ($keys as $key) {
            if (! isset($backup[$key]) || $backup[$key] === null) {
                delete_option($key);

                continue;
            }

            // No autoload argument: the plugin's own options are stored with
            // autoload off and WPLANG with it on, and passing a flag here would
            // quietly move one of them to the wrong side.
            update_option($key, $backup[$key]);
        }

        delete_transient('captchaapi_usage');
        delete_transient('captchaapi_service_state');
        unlink($backup_file);

        WP_CLI::success('Put the site back the way the run found it.');
        break;

    default:
        WP_CLI::error('Usage: wp eval-file state.php <backup|healthy|blocked|restore>');
}
