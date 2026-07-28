<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Server-side verification for the built-in WordPress forms. Each handler reads
 * the attestation from the POST body through the gate and blocks the submission
 * when it is missing, forged, expired, or replayed.
 */
class Captchaapi_Core_Forms
{
    private Captchaapi_Options $options;

    private Captchaapi_Gate $gate;

    public function __construct(Captchaapi_Options $options, Captchaapi_Gate $gate)
    {
        $this->options = $options;
        $this->gate    = $gate;
    }

    public function boot(): void
    {
        if ($this->options->protects('login')) {
            add_filter('authenticate', [$this, 'verify_login'], 30);
        }
        if ($this->options->protects('register')) {
            add_filter('registration_errors', [$this, 'verify_registration']);
        }
        if ($this->options->protects('lost_password')) {
            add_action('lostpassword_post', [$this, 'verify_lost_password']);
        }
        if ($this->options->protects('comments')) {
            add_filter('preprocess_comment', [$this, 'verify_comment']);
        }
    }

    /**
     * @param WP_User|WP_Error|null $user
     *
     * @return WP_User|WP_Error|null
     */
    public function verify_login($user)
    {
        // Only the wp-login.php form carries the "log" username field. App
        // passwords, XML-RPC, and programmatic sign-ins skip the captcha.
        if (! $this->is_form_submission() || ! isset($_POST['log'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return $user;
        }

        if ($this->gate->passes('login')) {
            return $user;
        }

        return new WP_Error('captchaapi_failed', $this->gate->error_message());
    }

    /**
     * @param WP_Error $errors
     *
     * @return WP_Error
     */
    public function verify_registration($errors)
    {
        if ($this->is_form_submission() && ! $this->gate->passes('register')) {
            $errors->add('captchaapi_failed', $this->gate->error_message());
        }

        return $errors;
    }

    /**
     * @param WP_Error $errors
     */
    public function verify_lost_password($errors): void
    {
        if ($this->is_form_submission() && is_wp_error($errors) && ! $this->gate->passes('lost_password')) {
            $errors->add('captchaapi_failed', $this->gate->error_message());
        }
    }

    /**
     * @param array<string, mixed> $comment_data
     *
     * @return array<string, mixed>
     */
    public function verify_comment($comment_data)
    {
        // XML-RPC and REST comment paths (Jetpack, mobile apps) carry no
        // attestation in $_POST, so let them through instead of blocking them.
        if (! $this->is_form_submission()) {
            return $comment_data;
        }

        $type = isset($comment_data['comment_type']) ? (string) $comment_data['comment_type'] : '';

        // Pingbacks and trackbacks are not browser submissions, so they have no
        // attestation to check.
        if ($type !== '' && $type !== 'comment') {
            return $comment_data;
        }

        if (! $this->gate->passes('comments')) {
            wp_die(
                esc_html($this->gate->error_message()),
                esc_html__('Comment blocked', 'captchaapi'),
                ['response' => 403, 'back_link' => true]
            );
        }

        return $comment_data;
    }

    private function is_form_submission(): bool
    {
        if (! isset($_SERVER['REQUEST_METHOD'])) {
            return false;
        }

        if (strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))) !== 'POST') {
            return false;
        }

        return ! (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
            && ! (defined('REST_REQUEST') && REST_REQUEST);
    }
}
