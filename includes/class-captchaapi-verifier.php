<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Verifies a captcha response with captchaapi.eu over a server-to-server call.
 *
 * The browser widget injects a single `captchaapi_response` value; we POST it to
 * /verify with the project secret as a Bearer token and trust the server's
 * `success` flag. The response is single-use - the server consumes it on the
 * first verify - so there is no retry. A 5xx or an unreachable host is reported
 * as "unavailable" so the gate's failsafe can decide; any other non-success is a
 * plain rejection.
 */
class Captchaapi_Verifier
{
    const VERIFIED = 'verified';

    const REJECTED = 'rejected';

    const UNAVAILABLE = 'unavailable';

    private string $secret_key;

    private string $verify_url;

    public function __construct(string $secret_key, string $verify_url)
    {
        $this->secret_key = $secret_key;
        $this->verify_url = $verify_url;
    }

    public function verify(string $response): string
    {
        if ($this->secret_key === '' || $response === '') {
            return self::REJECTED;
        }

        $result = wp_remote_post($this->verify_url, [
            'timeout' => 5,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['response' => $response]),
        ]);

        if (is_wp_error($result)) {
            return self::UNAVAILABLE;
        }

        $code = (int) wp_remote_retrieve_response_code($result);

        // A 5xx is our outage, not the visitor's fault - surface it as
        // unavailable so failsafe can let the submission through.
        if ($code >= 500) {
            return self::UNAVAILABLE;
        }

        $body = json_decode((string) wp_remote_retrieve_body($result), true);

        return is_array($body) && isset($body['success']) && $body['success'] === true
            ? self::VERIFIED
            : self::REJECTED;
    }
}
