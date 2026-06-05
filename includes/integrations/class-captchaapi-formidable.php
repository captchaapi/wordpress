<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Formidable Forms integration.
 *
 * The frontend gate in assets/js/captchaapi-ajax.js attaches a fresh attestation
 * before submission. The frm_validate_entry filter rejects the entry when the
 * attestation is missing, forged, expired, or replayed.
 */
class Captchaapi_Formidable
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
        return class_exists('FrmEntryValidate');
    }

    public function boot(): void
    {
        if (! $this->options->protects('formidable') || ! self::is_active()) {
            return;
        }

        add_filter('frm_validate_entry', [$this, 'verify'], 30, 2);
    }

    /**
     * @param array<string, mixed> $errors
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    public function verify($errors, $values = [])
    {
        if (! $this->gate->passes()) {
            $errors['captchaapi'] = $this->error_message();
        }

        return $errors;
    }

    private function error_message(): string
    {
        return __('Captcha verification failed. Reload the page and try again.', 'captchaapi');
    }
}
