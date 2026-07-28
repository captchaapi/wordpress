<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Runs the verification for a single request. Every protected form reads the
 * response the widget injected and asks the verifier whether captchaapi.eu
 * accepts it. Single-use enforcement is the server's job now, so there is no
 * local replay store.
 *
 * Three rules decide what happens when a submission does not clear:
 *
 *   1. A verdict about the submission always blocks. A forged or replayed
 *      response is a forged or replayed response, on every surface.
 *   2. An exhausted quota, a suspended account, or a project that is not set up
 *      also blocks. That is the free tier working as designed - the site owner
 *      has to act, and a silent downgrade to no protection would hide it.
 *   3. Our own outage defers to the failsafe setting. There is nothing for the
 *      owner to fix and nothing to buy, so punishing their visitors for it is a
 *      choice, not a default.
 *
 * ALWAYS_OPEN is the exception to rules 2 and 3. Blocking the login form would
 * shut the owner out of their own admin, where the notice, the settings, and the
 * link to a bigger plan all live. A locked-out owner cannot fix anything or pay
 * for anything, so those surfaces stay reachable no matter what our side says.
 * Rule 1 still applies to them: a forged response never gets in.
 */
class Captchaapi_Gate
{
    const FIELD = 'captchaapi_response';

    /**
     * Surfaces that are never closed over our own state - the way back into the
     * site has to stay open.
     *
     * @var array<int, string>
     */
    const ALWAYS_OPEN = [
        'login',
        'lost_password',
    ];

    private Captchaapi_Verifier $verifier;

    private Captchaapi_Service $service;

    private bool $failsafe;

    /**
     * @var array<string, bool>
     */
    private array $memo = [];

    private string $failure_reason = '';

    public function __construct(
        Captchaapi_Verifier $verifier,
        Captchaapi_Service $service,
        bool $failsafe = false
    ) {
        $this->verifier = $verifier;
        $this->service  = $service;
        $this->failsafe = $failsafe;
    }

    /**
     * @param string $surface One of the protect_* keys, so the gate knows
     *                        whether this is a surface it may close.
     */
    public function passes(string $surface = ''): bool
    {
        return $this->check($this->posted_response(), $surface);
    }

    /**
     * Same check as passes(), but for a response the integration already has in
     * hand. Fluent Forms (and similar) post the form fields nested inside a
     * single "data" parameter, so the response never lands in $_POST directly;
     * the integration reads it from the parsed form data and passes it here.
     */
    public function passes_for(string $response, string $surface = ''): bool
    {
        return $this->check($response, $surface);
    }

    /**
     * What to show the visitor when the gate closed. Administrators also get the
     * reason, because for them it is actionable; everyone else would only learn
     * something about our internals.
     */
    public function error_message(): string
    {
        $generic = __('Captcha verification failed. Reload the page and try again.', 'captchaapi');

        if ($this->failure_reason === '' || ! current_user_can('manage_options')) {
            return $generic;
        }

        return $generic . ' ' . $this->failure_reason;
    }

    /**
     * The response is single-use: the server consumes it on the first verify.
     * A form plugin that validates twice in one request would fail the second
     * call, so the verdict is memoized per response and the action fires once.
     */
    private function check(string $response, string $surface): bool
    {
        $key = $surface . '|' . $response;

        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $this->evaluate($response, $surface);

            /**
             * Fires once per protected form submission with the verification result.
             *
             * @param bool   $passed  Whether the submission cleared the gate.
             * @param string $surface The protect_* key of the form, when known.
             */
            do_action('captchaapi_verified', $this->memo[$key], $surface);
        }

        return $this->memo[$key];
    }

    private function evaluate(string $response, string $surface): bool
    {
        $this->failure_reason = '';

        // An empty field is either a stripped submission or a widget that never
        // got a challenge to solve. Identical from here, so ask the service
        // which of the two it is before turning a real visitor away.
        if ($response === '') {
            return $this->without_response($surface);
        }

        $status = $this->verifier->verify($response);

        if ($status === Captchaapi_Verifier::VERIFIED) {
            $this->service->remember(Captchaapi_Service::ENFORCING);

            return true;
        }

        if ($status === Captchaapi_Verifier::NOT_ENFORCEABLE) {
            $code = $this->verifier->last_error_code();
            $this->service->remember(Captchaapi_Service::NOT_ENFORCEABLE, $code);
            $this->failure_reason = self::reason_for($code);

            return $this->always_open($surface);
        }

        if ($status === Captchaapi_Verifier::UNAVAILABLE) {
            $this->service->remember(Captchaapi_Service::UNAVAILABLE);
            $this->failure_reason = __('The captchaapi.eu service could not be reached.', 'captchaapi');

            return $this->open_on_outage($surface);
        }

        return false;
    }

    /**
     * No response in the request. Only the service can say whether that means a
     * stripped field or a widget that had nothing to work with.
     */
    private function without_response(string $surface): bool
    {
        $state = $this->service->state();

        if ($state === Captchaapi_Service::ENFORCING) {
            return false;
        }

        if ($state === Captchaapi_Service::NOT_ENFORCEABLE) {
            $this->failure_reason = self::reason_for($this->service->blocking_code());

            return $this->always_open($surface);
        }

        $this->failure_reason = __('The captchaapi.eu service could not be reached.', 'captchaapi');

        return $this->open_on_outage($surface);
    }

    private function always_open(string $surface): bool
    {
        return in_array($surface, self::ALWAYS_OPEN, true);
    }

    private function open_on_outage(string $surface): bool
    {
        return $this->always_open($surface) || $this->failsafe;
    }

    /**
     * Turns an error code into something an administrator can act on.
     */
    public static function reason_for(string $code): string
    {
        switch ($code) {
            case 'free_tier_limit_reached':
                return __('The free tier limit for this billing period has been reached.', 'captchaapi');
            case 'account_suspended':
                return __('The captchaapi.eu account is suspended over billing.', 'captchaapi');
            case 'account_deactivated':
                return __('The captchaapi.eu account is deactivated.', 'captchaapi');
            case 'project_inactive':
                return __('The captchaapi.eu project is inactive.', 'captchaapi');
            case 'invalid_site_key':
                return __('The site key is not recognized.', 'captchaapi');
            case 'invalid_secret':
                return __('The secret key is not recognized.', 'captchaapi');
            case 'domain_not_allowed':
                return __('This domain is not in the project\'s allowed domains.', 'captchaapi');
            case '':
                return __('The captchaapi.eu service could not be reached.', 'captchaapi');
            default:
                // An unrecognised code is repeated back to the reader, and the
                // integrations hand this message to form plugins that print it
                // without escaping. It arrives over the network, so it is not
                // ours to trust however well we think we know the sender.
                return sprintf(
                    /* translators: %s: error code returned by the captchaapi.eu service. */
                    __('The captchaapi.eu service rejected the request (%s).', 'captchaapi'),
                    esc_html($code)
                );
        }
    }

    private function posted_response(): string
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if (empty($_POST[self::FIELD])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_POST[self::FIELD]));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
}
