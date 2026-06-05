<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Checks whether the captchaapi.eu service is reachable.
 *
 * Verification on submit is a local HMAC check and never calls the service, so
 * this is only used for two things: the "Test API response" button in the
 * settings, and failsafe mode, which lets submissions through while the service
 * is down so a brief outage does not lock real users out of every form.
 *
 * The result is cached for a couple of minutes so failsafe mode does not add an
 * HTTP request to each rejected submission during an outage.
 */
class Captchaapi_Service
{
    const TRANSIENT = 'captchaapi_service_reachable';

    private string $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function is_reachable(): bool
    {
        $cached = get_transient(self::TRANSIENT);

        if ($cached !== false) {
            return $cached === '1';
        }

        $reachable = $this->ping();
        set_transient(self::TRANSIENT, $reachable ? '1' : '0', 2 * MINUTE_IN_SECONDS);

        return $reachable;
    }

    public function ping(): bool
    {
        $response = wp_remote_get($this->url, [
            'timeout'     => 5,
            'redirection' => 2,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return $code >= 200 && $code < 400;
    }
}
