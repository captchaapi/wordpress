<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Answers one question: will the service issue challenges for this site right now?
 *
 * It matters because a widget that never received a challenge sends nothing, so
 * the form arrives with an empty field - indistinguishable from a stripped one.
 * The gate has to know which of the two it is looking at before it decides.
 *
 * Three answers, and they are not interchangeable:
 *
 *   ENFORCING       everything works; an empty field really is a stripped field
 *   NOT_ENFORCEABLE the account or the project stands in the way (quota used up,
 *                   suspended, inactive, wrong key). The site owner has to act,
 *                   so the gate keeps blocking and the admin notice says why
 *   UNAVAILABLE     we could not reach the service or it answered 5xx. Our
 *                   fault, nothing to buy, nothing for the owner to fix
 *
 * The verdict is cached so a spam run does not turn into one request per
 * submission, and the last blocking answer is stored so the admin notice can be
 * rendered without probing on every page load.
 */
class Captchaapi_Service
{
    const ENFORCING = 'enforcing';

    const NOT_ENFORCEABLE = 'not_enforceable';

    const UNAVAILABLE = 'unavailable';

    const TRANSIENT = 'captchaapi_service_state';

    const STATE_OPTION = 'captchaapi_last_service_state';

    const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

    /**
     * Error codes that mean the site cannot get challenges until the owner does
     * something about it. Anything else from the challenge endpoint is treated
     * as our problem.
     *
     * @var array<int, string>
     */
    const BLOCKING_CODES = [
        'free_tier_limit_reached',
        'account_suspended',
        'account_deactivated',
        'project_inactive',
        'invalid_site_key',
        'domain_not_allowed',
    ];

    private Captchaapi_Options $options;

    public function __construct(Captchaapi_Options $options)
    {
        $this->options = $options;
    }

    /**
     * One of the three state constants, cached for a few minutes.
     */
    public function state(): string
    {
        $cached = get_transient(self::TRANSIENT);

        if (is_array($cached) && isset($cached['state'])) {
            return (string) $cached['state'];
        }

        [$state, $code] = $this->probe();

        set_transient(self::TRANSIENT, ['state' => $state, 'code' => $code], self::CACHE_TTL);
        $this->remember($state, $code);

        return $state;
    }

    /**
     * The error code behind the current NOT_ENFORCEABLE state, or an empty
     * string when there is none.
     */
    public function blocking_code(): string
    {
        $cached = get_transient(self::TRANSIENT);

        if (is_array($cached) && isset($cached['code'])) {
            return (string) $cached['code'];
        }

        $remembered = get_option(self::STATE_OPTION);

        return (is_array($remembered) && isset($remembered['code'])) ? (string) $remembered['code'] : '';
    }

    /**
     * Records the latest verdict so the admin screens can report it without
     * making an HTTP request of their own. A healthy verdict clears the record.
     */
    public function remember(string $state, string $code = ''): void
    {
        // A real verification just told us more than a probe could, so the
        // cached verdict moves with it. Leaving it behind would let a stale
        // "everything is fine" answer outlive the account going over its limit,
        // and vice versa, for the length of the cache.
        set_transient(self::TRANSIENT, ['state' => $state, 'code' => $code], self::CACHE_TTL);

        $stored = get_option(self::STATE_OPTION);
        $stored = is_array($stored) ? $stored : null;

        if ($state === self::ENFORCING) {
            // Only on a real transition. Otherwise every verified submission on
            // the site would run a delete for a row that is not there.
            if ($stored !== null) {
                delete_option(self::STATE_OPTION);
            }

            return;
        }

        // A passing outage must not bury an account problem. One is a blip that
        // hides itself after an hour; the other needs the owner to act and is
        // cleared only by a submission that verifies.
        if ($state === self::UNAVAILABLE && $stored !== null && ($stored['state'] ?? '') === self::NOT_ENFORCEABLE) {
            return;
        }

        // Unchanged verdicts are not rewritten. The timestamp would differ every
        // time, so without this an outage means a wp_options write per request -
        // including every bot hitting the login form.
        if ($stored !== null && ($stored['state'] ?? '') === $state && ($stored['code'] ?? '') === $code) {
            return;
        }

        update_option(
            self::STATE_OPTION,
            ['state' => $state, 'code' => $code, 'time' => time()],
            false
        );
    }

    /**
     * The last recorded non-healthy verdict: state, code, and when it was seen.
     *
     * @return array{state: string, code: string, time: int}|null
     */
    public function last_problem(): ?array
    {
        $remembered = get_option(self::STATE_OPTION);

        if (! is_array($remembered) || empty($remembered['state'])) {
            return null;
        }

        return [
            'state' => (string) $remembered['state'],
            'code'  => isset($remembered['code']) ? (string) $remembered['code'] : '',
            'time'  => isset($remembered['time']) ? (int) $remembered['time'] : 0,
        ];
    }

    /**
     * Asks the challenge endpoint what it would do for a real browser request.
     *
     * @return array{0: string, 1: string} state and the error code behind it
     */
    private function probe(): array
    {
        $site_key = $this->options->site_key();

        if ($site_key === '') {
            return [self::NOT_ENFORCEABLE, 'invalid_site_key'];
        }

        $response = wp_remote_post($this->options->api_url() . '/captcha/challenge', [
            'timeout' => 3,
            'headers' => [
                'Content-Type' => 'application/json',
                'Origin'       => $this->site_origin(),
            ],
            'body'    => wp_json_encode(['site_key' => $site_key]),
        ]);

        if (is_wp_error($response)) {
            return [self::UNAVAILABLE, ''];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code === 200) {
            return [self::ENFORCING, ''];
        }

        if ($code >= 500) {
            return [self::UNAVAILABLE, ''];
        }

        $body       = json_decode((string) wp_remote_retrieve_body($response), true);
        $error_code = (is_array($body) && isset($body['error_code'])) ? (string) $body['error_code'] : '';

        return in_array($error_code, self::BLOCKING_CODES, true)
            ? [self::NOT_ENFORCEABLE, $error_code]
            : [self::UNAVAILABLE, $error_code];
    }

    private function site_origin(): string
    {
        $parts = wp_parse_url(home_url());

        if (! is_array($parts) || empty($parts['host'])) {
            return home_url();
        }

        $origin = (isset($parts['scheme']) ? $parts['scheme'] : 'https') . '://' . $parts['host'];

        return isset($parts['port']) ? $origin . ':' . $parts['port'] : $origin;
    }
}
