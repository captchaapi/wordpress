<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Reads this period's totals from the service.
 *
 * The numbers move once every fifteen minutes at best, so they are cached for
 * half a day: this drives one admin screen, not a dashboard anybody watches.
 *
 * Everything about this class is built to be skippable. An admin screen must
 * never wait on someone else's network, and outbound connections are blocked or
 * slow on a great deal of shared hosting - so the timeout is short, a failure is
 * not retried until the cache expires, and a failure renders nothing at all
 * rather than an error the reader can do nothing about. A plugin that is
 * otherwise working must not look broken because a status call did not answer.
 */
class Captchaapi_Usage
{
    const TRANSIENT = 'captchaapi_usage';

    const CACHE_TTL = 12 * HOUR_IN_SECONDS;

    /**
     * How long a failure is remembered. Shorter than a success: a firewall that
     * has just been opened, or an endpoint that has just been deployed, should
     * not take half a day to be noticed.
     */
    const FAILURE_TTL = HOUR_IN_SECONDS;

    private Captchaapi_Options $options;

    public function __construct(Captchaapi_Options $options)
    {
        $this->options = $options;
    }

    /**
     * The period's figures, or null when they are not available - which is a
     * normal state, not an error worth reporting.
     *
     * @return array{period_start: string, challenges: int, verified: int, used: int, limit: ?int, tier: string, stale_after: int}|null
     */
    public function current(): ?array
    {
        $cached = get_transient(self::TRANSIENT);

        if (is_array($cached)) {
            return $cached;
        }

        if ($cached === 'unavailable') {
            return null;
        }

        $usage = $this->fetch();

        if ($usage === null) {
            set_transient(self::TRANSIENT, 'unavailable', self::FAILURE_TTL);

            return null;
        }

        set_transient(self::TRANSIENT, $usage, self::CACHE_TTL);

        return $usage;
    }

    /**
     * Drops the cache so the next read goes to the service. For the moment
     * after someone changes their keys or fixes their plan.
     */
    public function forget(): void
    {
        delete_transient(self::TRANSIENT);
    }

    /**
     * @return array{period_start: string, challenges: int, verified: int, used: int, limit: ?int, tier: string, stale_after: int}|null
     */
    private function fetch(): ?array
    {
        $secret = $this->options->current_secret_key();

        if ($secret === '') {
            return null;
        }

        $response = wp_remote_get($this->options->api_url() . '/stats', [
            'timeout' => 3,
            'headers' => [
                'Authorization' => 'Bearer ' . $secret,
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        if (! is_array($body) || ! isset($body['account']) || ! is_array($body['account'])) {
            return null;
        }

        $account = $body['account'];

        return [
            'period_start' => isset($body['period_start']) ? (string) $body['period_start'] : '',
            'challenges'   => (int) ($body['challenges'] ?? 0),
            'verified'     => (int) ($body['verified'] ?? 0),
            'used'         => (int) ($account['used'] ?? 0),
            // A null cap means the account is quota-exempt. It is not the same
            // as zero, and it must not be replaced with a figure for the tier -
            // the service is the only thing that knows.
            'limit'        => isset($account['limit']) && $account['limit'] !== null ? (int) $account['limit'] : null,
            'tier'         => isset($account['tier']) ? (string) $account['tier'] : '',
            // How far behind live traffic the service says these can be. Read
            // rather than assumed: the flush cadence is theirs to change.
            'stale_after'  => (int) ($body['stale_after_seconds'] ?? 0),
        ];
    }
}
