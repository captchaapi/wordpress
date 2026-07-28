<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Gravity Forms integration.
 *
 * The frontend gate in assets/js/captchaapi-ajax.js attaches a fresh attestation
 * before submission. The gform_validation filter marks the submission invalid
 * when the attestation is missing, forged, expired, or replayed.
 *
 * Gravity Forms is commercial and is not part of the local test environment, so
 * this integration is wired from its documented hooks but has not been verified
 * against a running install.
 */
class Captchaapi_Gravity_Forms
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
        return class_exists('GFForms');
    }

    public function boot(): void
    {
        if (! $this->options->protects('gravityforms') || ! self::is_active()) {
            return;
        }

        add_filter('gform_validation', [$this, 'verify'], 30);
    }

    /**
     * @param array<string, mixed> $validation_result
     *
     * @return array<string, mixed>
     */
    public function verify($validation_result)
    {
        if (! $this->gate->passes('gravityforms')) {
            $validation_result['is_valid'] = false;
        }

        return $validation_result;
    }
}
