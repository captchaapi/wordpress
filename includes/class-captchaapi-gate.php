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

    public function __construct(Captchaapi_Verifier $verifier, Captchaapi_Replay_Store $replay_store)
    {
        $this->verifier     = $verifier;
        $this->replay_store = $replay_store;
    }

    /**
     * True only for a genuine, unexpired, previously unused attestation on the
     * current request. The attestation is HMAC-verified, so the POST field is its
     * own proof of authenticity and needs no separate nonce.
     */
    public function passes(): bool
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if (empty($_POST[self::FIELD])) {
            return false;
        }

        $attestation = sanitize_text_field(wp_unslash($_POST[self::FIELD]));
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $payload = $this->verifier->verify($attestation);
        if ($payload === null) {
            return false;
        }

        $jti = (isset($payload['jti']) && is_string($payload['jti'])) ? $payload['jti'] : '';
        $ttl = (int) ($payload['exp'] ?? 0) - time();

        return $this->replay_store->claim($jti, $ttl);
    }
}
