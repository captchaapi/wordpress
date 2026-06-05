<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WPForms integration.
 *
 * The form submits over WPForms' own flow, so the frontend gate in
 * assets/js/captchaapi-ajax.js attaches a fresh attestation before submission.
 * Here the wpforms_process action rejects the entry when the attestation is
 * missing, forged, expired, or replayed by writing into the form's error bag.
 */
class Captchaapi_WPForms
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
        return function_exists('wpforms');
    }

    public function boot(): void
    {
        if (! $this->options->protects('wpforms') || ! self::is_active()) {
            return;
        }

        add_action('wpforms_process', [$this, 'verify'], 30, 3);
    }

    /**
     * @param array<int, mixed>    $fields
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $form_data
     */
    public function verify($fields, $entry, $form_data): void
    {
        if ($this->gate->passes()) {
            return;
        }

        $form_id = isset($form_data['id']) ? absint($form_data['id']) : 0;

        if ($form_id > 0 && isset(wpforms()->process)) {
            wpforms()->process->errors[$form_id]['header'] = $this->error_message();
        }
    }

    private function error_message(): string
    {
        return __('Captcha verification failed. Reload the page and try again.', 'captchaapi');
    }
}
