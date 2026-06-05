<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Fluent Forms integration.
 *
 * Fluent Forms submits over its own AJAX, so the frontend gate in
 * assets/js/captchaapi-ajax.js attaches a fresh attestation. The
 * fluentform/validation_errors filter rejects the submission when the
 * attestation is missing, forged, expired, or replayed.
 */
class Captchaapi_Fluent_Forms
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
        return defined('FLUENTFORM_VERSION');
    }

    public function boot(): void
    {
        if (! $this->options->protects('fluentform') || ! self::is_active()) {
            return;
        }

        add_filter('fluentform/validation_errors', [$this, 'verify'], 30);
    }

    /**
     * @param array<string, mixed> $errors
     *
     * @return array<string, mixed>
     */
    public function verify($errors)
    {
        if (! $this->gate->passes_for($this->posted_attestation())) {
            $errors['captcha_attestation'] = $this->error_message();
        }

        return $errors;
    }

    /**
     * Fluent Forms posts the whole form as one urlencoded "data" parameter and
     * strips unknown fields from the parsed form data, so the attestation never
     * reaches the validation filter. It is read from the raw request instead.
     */
    private function posted_attestation(): string
    {
        // The attestation is its own HMAC proof, so it needs no nonce, and the
        // value pulled out of the parsed string is sanitized before use.
        // phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (empty($_POST['data'])) {
            return '';
        }

        parse_str(wp_unslash($_POST['data']), $parsed);
        // phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        return isset($parsed['captcha_attestation']) ? sanitize_text_field((string) $parsed['captcha_attestation']) : '';
    }

    private function error_message(): string
    {
        return __('Captcha verification failed. Reload the page and try again.', 'captchaapi');
    }
}
