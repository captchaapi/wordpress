<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Ties verification and replay protection together for a single request. Every
 * protected form runs the same check: read the attestation from the POST body,
 * confirm it is genuine and fresh, then consume its jti so it cannot be reused.
 */
class Captchaapi_Gate
{
    const FIELD = 'captcha_attestation';

    private Captchaapi_Verifier $verifier;

    private Captchaapi_Replay_Store $replay_store;

    private bool $failsafe;

    private ?Captchaapi_Service $service;

    public function __construct(
        Captchaapi_Verifier $verifier,
        Captchaapi_Replay_Store $replay_store,
        bool $failsafe = false,
        ?Captchaapi_Service $service = null
    ) {
        $this->verifier     = $verifier;
        $this->replay_store = $replay_store;
        $this->failsafe     = $failsafe;
        $this->service      = $service;
    }

    /**
     * True for a genuine, unexpired, previously unused attestation. In failsafe
     * mode it is also true when the service is unreachable: during an outage the
     * widget cannot mint an attestation, so blocking would lock real users out of
     * every form. The reachability check only runs after normal verification has
     * already failed, so a valid submission never pays for it.
     */
    public function passes(): bool
    {
        return $this->check($this->posted_attestation());
    }

    /**
     * Same check as passes(), but for an attestation the integration already has
     * in hand. Fluent Forms (and similar) post the form fields nested inside a
     * single "data" parameter, so the attestation never lands in $_POST directly;
     * the integration reads it from the parsed form data and passes it here.
     */
    public function passes_for(string $attestation): bool
    {
        return $this->check($attestation);
    }

    /**
     * The attestation is HMAC-verified, so it is its own proof of authenticity and
     * needs no separate nonce. In failsafe mode the check also succeeds when the
     * service is unreachable: during an outage the widget cannot mint an
     * attestation, so blocking would lock real users out of every form. The
     * reachability check only runs after normal verification has failed, so a
     * valid submission never pays for it.
     */
    private function check(string $attestation): bool
    {
        $passed = $this->evaluate($attestation);

        /**
         * Fires once per protected form submission with the verification result.
         * Useful for logging or monitoring how often the gate passes or blocks.
         *
         * @param bool $passed Whether the submission cleared the gate.
         */
        do_action('captchaapi_verified', $passed);

        return $passed;
    }

    private function evaluate(string $attestation): bool
    {
        if ($attestation !== '') {
            $payload = $this->verifier->verify($attestation);
            if ($payload !== null) {
                $jti = (isset($payload['jti']) && is_string($payload['jti'])) ? $payload['jti'] : '';
                $ttl = (int) ($payload['exp'] ?? 0) - time();

                if ($this->replay_store->claim($jti, $ttl)) {
                    return true;
                }
            }
        }

        return $this->failsafe
            && $this->service !== null
            && ! $this->service->is_reachable();
    }

    private function posted_attestation(): string
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if (empty($_POST[self::FIELD])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_POST[self::FIELD]));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
}
