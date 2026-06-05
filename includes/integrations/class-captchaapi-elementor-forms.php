<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Elementor Pro Forms integration.
 *
 * The frontend gate in assets/js/captchaapi-ajax.js attaches a fresh attestation
 * before submission. The elementor_pro/forms/validation action rejects the
 * submission when the attestation is missing, forged, expired, or replayed.
 *
 * Elementor Pro is commercial and is not part of the local test environment, so
 * this integration is wired from its documented hooks but has not been verified
 * against a running install.
 */
class Captchaapi_Elementor_Forms
{
    private Captchaapi_Options $options;

    private Captchaapi_Gate $gate;

    public function __construct(Captchaapi_Options $options, Captchaapi_Gate $gate)
    {
        $this->options = $options;
        $this->gate    = $gate;
    }

    public static function is_active(): bool
    {
        return class_exists('ElementorPro\\Plugin');
    }

    public function boot(): void
    {
        if (! $this->options->protects('elementor_forms') || ! self::is_active()) {
            return;
        }

        add_action('elementor_pro/forms/validation', [$this, 'verify'], 30, 2);
    }

    /**
     * @param mixed $record
     * @param mixed $ajax_handler
     */
    public function verify($record, $ajax_handler): void
    {
        if ($this->gate->passes()) {
            return;
        }

        if (is_object($ajax_handler) && method_exists($ajax_handler, 'add_error_message')) {
            $ajax_handler->add_error_message($this->error_message());
        }
    }

    private function error_message(): string
    {
        return __('Captcha verification failed. Reload the page and try again.', 'captchaapi');
    }
}
