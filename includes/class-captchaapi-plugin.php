<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Wires the plugin together. The settings page always loads so the keys can be
 * entered; everything else stays dormant until a site key and a secret key are
 * present.
 */
class Captchaapi_Plugin
{
    const LEGACY_PURGE_HOOK = 'captchaapi_purge_expired';

    const LEGACY_TABLE = 'captchaapi_used_attestations';

    private Captchaapi_Options $options;

    public function __construct(Captchaapi_Options $options)
    {
        $this->options = $options;
    }

    public function boot(): void
    {
        $this->maybe_upgrade();

        $stats = new Captchaapi_Stats();
        $stats->boot();

        (new Captchaapi_Settings($this->options, $stats, new Captchaapi_Usage($this->options)))->boot();

        // Before the is_configured() gate on purpose: connecting is what a
        // site with no keys yet is here to do.
        (new Captchaapi_Connect($this->options))->boot();

        if (! $this->options->is_configured()) {
            return;
        }

        (new Captchaapi_Assets($this->options))->boot();

        $service = new Captchaapi_Service($this->options);

        (new Captchaapi_Notices($this->options, $service))->boot();

        $gate = new Captchaapi_Gate(
            new Captchaapi_Verifier(
                $this->options->current_secret_key(),
                $this->options->api_url() . '/captcha/verify'
            ),
            $service,
            $this->options->failsafe()
        );

        (new Captchaapi_Core_Forms($this->options, $gate))->boot();
        (new Captchaapi_Contact_Form_7($this->options, $gate))->boot();
        (new Captchaapi_WooCommerce($this->options, $gate))->boot();
        (new Captchaapi_WPForms($this->options, $gate))->boot();
        (new Captchaapi_Fluent_Forms($this->options, $gate))->boot();
        (new Captchaapi_Formidable($this->options, $gate))->boot();
        (new Captchaapi_Forminator($this->options, $gate))->boot();
        (new Captchaapi_Gravity_Forms($this->options, $gate))->boot();
        (new Captchaapi_Elementor_Forms($this->options, $gate))->boot();
    }

    /**
     * One-time cleanup when upgrading from a version that kept a local
     * single-use store. The server owns single-use now, so the table and its
     * purge cron are dead weight; drop them and remember the version.
     */
    private function maybe_upgrade(): void
    {
        if (get_option('captchaapi_version') === CAPTCHAAPI_VERSION) {
            return;
        }

        self::remove_legacy_replay_store();

        update_option('captchaapi_version', CAPTCHAAPI_VERSION);
    }

    public static function activate(): void
    {
        self::remove_legacy_replay_store();

        update_option('captchaapi_version', CAPTCHAAPI_VERSION);
    }

    public static function deactivate(): void
    {
        self::remove_legacy_replay_store();

        // An interrupted connect leaves a PKCE verifier behind; options have
        // no expiry of their own, so drop it rather than let it sit.
        delete_option(Captchaapi_Connect::PENDING_OPTION);
    }

    public static function remove_legacy_replay_store(): void
    {
        $timestamp = wp_next_scheduled(self::LEGACY_PURGE_HOOK);

        if ($timestamp) {
            wp_unschedule_event($timestamp, self::LEGACY_PURGE_HOOK);
        }

        global $wpdb;

        $table = $wpdb->prefix . self::LEGACY_TABLE;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is a fixed prefix plus a constant string, not user input
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
