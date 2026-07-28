<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce integration.
 *
 * The My Account login, registration, and lost-password forms submit with a
 * native POST, so they reuse the marker mechanism in Captchaapi_Assets and are
 * verified through WooCommerce's own validation filters. Lost password is not
 * handled here: WooCommerce fires the core lostpassword_post action, which
 * Captchaapi_Core_Forms already verifies when that surface is enabled. Hooking
 * it twice would let the first handler consume the attestation jti and make the
 * second fail.
 *
 * Checkout submits over WooCommerce's AJAX, so it needs the frontend gate in
 * assets/js/captchaapi-woocommerce.js to attach a fresh attestation before the
 * order is placed, plus the woocommerce_checkout_process check below.
 */
class Captchaapi_WooCommerce
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
        return class_exists('WooCommerce');
    }

    public function boot(): void
    {
        if (! self::is_active()) {
            return;
        }

        if ($this->options->protects('login')) {
            add_filter('woocommerce_process_login_errors', [$this, 'verify_login'], 30);
        }
        if ($this->options->protects('register')) {
            add_filter('woocommerce_process_registration_errors', [$this, 'verify_registration'], 30);
        }
        if ($this->options->protects('woo_checkout')) {
            add_action('woocommerce_checkout_process', [$this, 'verify_checkout']);
        }
    }

    /**
     * @param WP_Error $validation_error
     *
     * @return WP_Error
     */
    public function verify_login($validation_error)
    {
        if (! $this->gate->passes('login')) {
            $validation_error->add('captchaapi_failed', $this->gate->error_message());
        }

        return $validation_error;
    }

    /**
     * @param WP_Error $validation_error
     *
     * @return WP_Error
     */
    public function verify_registration($validation_error)
    {
        if (! $this->gate->passes('register')) {
            $validation_error->add('captchaapi_failed', $this->gate->error_message());
        }

        return $validation_error;
    }

    public function verify_checkout(): void
    {
        if (! $this->gate->passes('woo_checkout')) {
            wc_add_notice($this->gate->error_message(), 'error');
        }
    }
}
