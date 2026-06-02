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
    private Captchaapi_Options $options;

    public function __construct(Captchaapi_Options $options)
    {
        $this->options = $options;
    }

    public function boot(): void
    {
        add_action(Captchaapi_Replay_Store::PURGE_HOOK, [$this, 'purge_expired']);

        (new Captchaapi_Settings($this->options))->boot();

        if (! $this->options->is_configured()) {
            return;
        }

        (new Captchaapi_Assets($this->options))->boot();

        $gate = new Captchaapi_Gate(
            new Captchaapi_Verifier($this->options->secret_keys(), $this->options->site_key()),
            new Captchaapi_Replay_Store()
        );

        (new Captchaapi_Core_Forms($this->options, $gate))->boot();
        (new Captchaapi_Contact_Form_7($this->options, $gate))->boot();
    }

    public function purge_expired(): void
    {
        (new Captchaapi_Replay_Store())->purge_expired();
    }

    public static function activate(): void
    {
        Captchaapi_Replay_Store::create_table();

        if (! wp_next_scheduled(Captchaapi_Replay_Store::PURGE_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', Captchaapi_Replay_Store::PURGE_HOOK);
        }
    }

    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled(Captchaapi_Replay_Store::PURGE_HOOK);

        if ($timestamp) {
            wp_unschedule_event($timestamp, Captchaapi_Replay_Store::PURGE_HOOK);
        }
    }
}
