<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Verifies a captcha attestation locally. No call back to captchaapi.eu is made:
 * the attestation is an HMAC-SHA256 signature the project's secret key can check
 * on its own.
 *
 * An attestation is "base64url(payload).base64url(signature)". Verification
 * proves the payload was signed by one of our secret keys, has not expired, and
 * was minted for this site key. Single-use enforcement (the jti) lives in
 * Captchaapi_Replay_Store, not here.
 */
class Captchaapi_Verifier
{
    /**
     * @var array<int, string>
     */
    private array $secret_keys;

    private string $site_key;

    /**
     * @param array<int, string> $secret_keys Any key in the list is accepted, which allows zero-downtime rotation.
     */
    public function __construct(array $secret_keys, string $site_key)
    {
        $this->secret_keys = $secret_keys;
        $this->site_key    = $site_key;
    }

    /**
     * Returns the decoded payload when the attestation is genuine, unexpired, and
     * bound to this site key. Returns null on any failure.
     *
     * @return array<string, mixed>|null
     */
    public function verify(string $attestation): ?array
    {
        if ($this->secret_keys === [] || strpos($attestation, '.') === false) {
            return null;
        }

        list($payload_b64, $signature_b64) = explode('.', $attestation, 2);

        $signature = base64_decode(strtr($signature_b64, '-_', '+/'), true);
        if ($signature === false) {
            return null;
        }

        if (! $this->signature_matches($payload_b64, $signature)) {
            return null;
        }

        $payload_json = base64_decode(strtr($payload_b64, '-_', '+/'), true);
        if ($payload_json === false) {
            return null;
        }

        $payload = json_decode($payload_json, true);
        if (! is_array($payload)) {
            return null;
        }

        if ((int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        if (($payload['sk'] ?? '') !== $this->site_key) {
            return null;
        }

        return $payload;
    }

    private function signature_matches(string $payload_b64, string $signature): bool
    {
        foreach ($this->secret_keys as $secret_key) {
            $expected = hash_hmac('sha256', $payload_b64, $secret_key, true);
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
