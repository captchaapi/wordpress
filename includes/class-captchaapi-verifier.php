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
 * first verify - so there is no retry.
 *
 * The four outcomes are deliberately distinct, because the gate treats them
 * differently:
 *
 *   VERIFIED        the submission is genuine
 *   REJECTED        a verdict about the submission itself - forged or replayed
 *   NOT_ENFORCEABLE the account or the configuration is in the way, not the
 *                   visitor. The site owner has to act
 *   UNAVAILABLE     unreachable or 5xx - our outage, nobody's fault
 */
class Captchaapi_Verifier
{
    const VERIFIED = 'verified';

    const REJECTED = 'rejected';

    const NOT_ENFORCEABLE = 'not_enforceable';

    const UNAVAILABLE = 'unavailable';

    /**
     * The only failures that are a verdict about the submission. Everything
     * else the endpoint can return describes the account, the configuration, or
     * us - never the visitor.
     *
     * @var array<int, string>
     */
    const VISITOR_FAULT = [
        'invalid_token',
        'invalid_solution',
    ];

    private string $secret_key;

    private string $verify_url;

    private string $last_error_code = '';

    public function __construct(string $secret_key, string $verify_url)
    {
        $this->secret_key = $secret_key;
        $this->verify_url = $verify_url;
    }

    public function verify(string $response): string
    {
        $this->last_error_code = '';

        if ($response === '') {
            return self::REJECTED;
        }

        if ($this->secret_key === '') {
            $this->last_error_code = 'invalid_secret';

            return self::NOT_ENFORCEABLE;
        }

        $result = wp_remote_post($this->verify_url, [
            'timeout' => 3,
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
        // unavailable so failsafe can decide.
        if ($code >= 500) {
            return self::UNAVAILABLE;
        }

        $body = json_decode((string) wp_remote_retrieve_body($result), true);

        if (is_array($body) && isset($body['success']) && $body['success'] === true) {
            return self::VERIFIED;
        }

        $error_code            = (is_array($body) && isset($body['error_code'])) ? (string) $body['error_code'] : '';
        $this->last_error_code = $error_code;

        // Only a verdict about the submission rejects it. A bad secret, an empty
        // body, an unrecognised code: none of them accuse the visitor.
        return in_array($error_code, self::VISITOR_FAULT, true)
            ? self::REJECTED
            : self::NOT_ENFORCEABLE;
    }

    /**
     * The error code from the most recent verify, for whoever needs to explain
     * the outcome. Empty when the call succeeded or never reached the service.
     */
    public function last_error_code(): string
    {
        return $this->last_error_code;
    }
}
