<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Forminator integration.
 *
 * The frontend gate in assets/js/captchaapi-ajax.js attaches a fresh attestation
 * before submission. The forminator_custom_form_submit_errors filter rejects the
 * submission when the attestation is missing, forged, expired, or replayed.
 * Forminator aborts whenever the error list is non-empty.
 */
class Captchaapi_Forminator
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
        return class_exists('Forminator');
    }

    public function boot(): void
    {
        if (! $this->options->protects('forminator') || ! self::is_active()) {
            return;
        }

        add_filter('forminator_custom_form_submit_errors', [$this, 'verify'], 30);
    }

    /**
     * @param array<int, mixed> $submit_errors
     *
     * @return array<int, mixed>
     */
    public function verify($submit_errors)
    {
        if (! is_array($submit_errors)) {
            $submit_errors = [];
        }

        if (! $this->gate->passes()) {
            $submit_errors[] = ['captchaapi' => $this->error_message()];
        }

        return $submit_errors;
    }

    private function error_message(): string
    {
        return __('Captcha verification failed. Reload the page and try again.', 'captchaapi');
    }
}
