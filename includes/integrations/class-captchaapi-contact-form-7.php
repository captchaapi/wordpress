<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Contact Form 7 integration.
 *
 * CF7 submits over its own AJAX and registers a submit listener that fires
 * unconditionally, so the widget's submit mode can't be used. The frontend gate
 * in assets/js/captchaapi-cf7.js calls window.captchaapi.solve() to attach a
 * fresh attestation before letting CF7 send the form. Here we close the loop on
 * the server: a missing or invalid attestation marks the submission as spam,
 * which CF7 blocks.
 */
class Captchaapi_Contact_Form_7
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
        return defined('WPCF7_VERSION') || class_exists('WPCF7');
    }

    public function boot(): void
    {
        if (! $this->options->protects('cf7') || ! self::is_active()) {
            return;
        }

        add_filter('wpcf7_spam', [$this, 'verify'], 10, 2);
    }

    /**
     * @param bool  $spam
     * @param mixed $submission
     */
    public function verify($spam, $submission = null): bool
    {
        if ($spam) {
            return true;
        }

        return ! $this->gate->passes();
    }
}
