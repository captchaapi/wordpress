<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * One-click key setup. The admin is sent to captchaapi.eu, approves the
 * connection there, and comes back with a short-lived code that this plugin
 * trades for the project's keys over a server-to-server call.
 *
 * The plugin has no client secret, so PKCE is what ties the returning code
 * to the request that started it: the verifier stays here, only its SHA-256
 * hash travels.
 */
class Captchaapi_Connect
{
    const START_ACTION = 'captchaapi_connect_start';

    const PENDING_OPTION = 'captchaapi_connect_pending';

    /**
     * How the outcome survives the redirect that strips the code from the URL.
     * Written by finish(), read back by render_result().
     */
    const RESULT_ARG = 'captchaapi_connect';

    const RESULT_OK = 'ok';

    const RESULT_CONFIGURED = 'configured';

    const RESULT_STATE = 'state';

    const RESULT_EXCHANGE = 'exchange';

    const RESULT_HOST = 'host';

    /**
     * Sanity bounds on what the exchange will accept back. Deliberately loose -
     * they are here to stop a buggy or impersonated response from parking
     * megabytes in an autoloaded option that every page load then reads, not to
     * assert a key format. Pinning the `pk_`/`sk_` prefixes would put the
     * plugin's idea of a key ahead of the server's, and the day the server
     * changes one, connect would break on sites that cannot be updated.
     */
    const MAX_KEY_LENGTH = 256;

    const MAX_BODY_LENGTH = 8192;

    /**
     * An option, not a transient. A persistent object cache can evict a
     * transient at any moment, and a legitimate connection failing halfway
     * because the cache felt like it is not a trade worth making.
     *
     * Because options have no expiry of their own, every read checks the
     * timestamp stored inside the value.
     */
    const PENDING_TTL = 900;

    private Captchaapi_Options $options;

    public function __construct(Captchaapi_Options $options)
    {
        $this->options = $options;
    }

    public function boot(): void
    {
        add_action('admin_post_' . self::START_ACTION, [$this, 'start']);
        add_action('admin_init', [$this, 'maybe_handle_return']);
    }

    /**
     * Kick off the flow: stash the state and the PKCE verifier, then hand the
     * admin over to captchaapi.eu.
     */
    public function start(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Not allowed.', 'captchaapi'), '', ['response' => 403]);
        }

        check_admin_referer(self::START_ACTION);

        $host = $this->site_host();

        // render_button() hides itself in this case, so getting here means the
        // URL was reached some other way. Stopping before a verifier is written
        // keeps a hostless site from leaving one behind for nothing.
        if ($host === '') {
            $this->finish(self::RESULT_HOST);
        }

        $state    = bin2hex(random_bytes(16));
        $verifier = self::base64url(random_bytes(48));
        $return   = $this->return_url();

        $this->remember([
            'state'    => $state,
            'verifier' => $verifier,
            'return'   => $return,
            'created'  => time(),
        ]);

        $url = add_query_arg(
            [
                'return'         => rawurlencode($return),
                'state'          => $state,
                'code_challenge' => self::base64url(hash('sha256', $verifier, true)),
                'domain'         => rawurlencode($host),
            ],
            $this->options->connect_base_url() . '/connect'
        );

        wp_redirect($url);
        exit;
    }

    /**
     * Handle the hop back from captchaapi.eu. Runs on every admin request, so
     * it bails out immediately unless our own parameters are present.
     */
    public function maybe_handle_return(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only; the state below is the CSRF token for this hop, and it is compared before anything is acted on.
        if (! isset($_GET['captchaapi_code'], $_GET['state'])) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
        $code = sanitize_text_field(wp_unslash($_GET['captchaapi_code']));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
        $state = sanitize_text_field(wp_unslash($_GET['state']));

        $pending = $this->pending();

        // Nothing was started from this screen, so there is nothing to finish
        // and nothing to report. Bail without redirecting: these two parameters
        // can be hung off any admin URL, and anyone who gets an administrator to
        // load one - an image tag in a mail is enough - would otherwise bounce
        // them out of whatever page they were on.
        if ($pending === null) {
            return;
        }

        // The state is the CSRF token for this hop, so nothing below it - not
        // even clearing the attempt - may run until it matches. Checking it
        // first is what stops a forged return from wiping a verifier that a
        // real connect is still waiting on.
        if (! hash_equals($pending['state'], $state)) {
            $this->finish(self::RESULT_STATE);
        }

        // Past the state check the attempt is genuinely ours, and it is over
        // either way: a failed exchange must not leave the verifier sitting in
        // the database, and a second load of the same URL must not replay it.
        $this->forget();

        // The button hides itself on anything but a blank slate, but a return
        // URL kept open in a second tab does not. The test has to be the same
        // one render_button() uses - "either key present" rather than
        // is_configured()'s "both keys present" - or a key typed in by hand
        // while the flow was open gets overwritten, and a key pinned by a
        // wp-config constant leaves the pair mismatched: the constant keeps
        // serving the old project while the freshly issued sibling goes live.
        if ($this->has_any_key()) {
            $this->finish(self::RESULT_CONFIGURED);
        }

        $keys = $this->exchange($code, $pending['verifier'], $pending['return']);

        if ($keys === null) {
            $this->finish(self::RESULT_EXCHANGE);
        }

        $stored                = $this->options->all();
        $stored['site_key']    = $keys['site_key'];
        $stored['secret_key']  = $keys['secret_key'];
        update_option(Captchaapi_Options::OPTION_KEY, $stored);

        $this->finish(self::RESULT_OK);
    }

    /**
     * Outcome of a round-trip, rendered on the settings screen. finish() leaves
     * only this flag behind, so the notice survives the redirect that keeps the
     * code out of the address bar.
     */
    public function render_result(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag written by our own wp_safe_redirect; it carries no privilege and triggers no action.
        $result = isset($_GET[self::RESULT_ARG]) ? sanitize_key(wp_unslash($_GET[self::RESULT_ARG])) : '';

        if ($result === '') {
            return;
        }

        // The flag is a plain query argument, so anyone who gets an administrator
        // to open a link can ask for any of these notices. That is display-only
        // and it is how WordPress itself carries settings-updated, but claiming
        // a connection that did not happen would send the reader off to look for
        // keys that are not there. The two outcomes that assert something about
        // stored state are checked against that state before they are shown.
        if ($result === self::RESULT_OK) {
            if (! $this->options->is_configured()) {
                return;
            }

            printf(
                '<div class="notice notice-success"><p>%s</p></div>',
                esc_html__('Connected. Your site key and secret key are filled in below.', 'captchaapi')
            );

            return;
        }

        if ($result === self::RESULT_CONFIGURED && ! $this->has_any_key()) {
            return;
        }

        if ($result === self::RESULT_CONFIGURED) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__('This site already has a key of its own, so nothing was changed. Clear both key fields first if you really meant to connect a different account.', 'captchaapi')
            );

            return;
        }

        if ($result === self::RESULT_HOST) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('This site has no address to register - check the WordPress Address and Site Address under Settings -> General, then try again.', 'captchaapi')
            );

            return;
        }

        $message = $result === self::RESULT_STATE
            ? __('That connection attempt did not match the one started from this screen, so nothing was saved. Start it again, or paste your keys in by hand.', 'captchaapi')
            : __('captchaapi.eu could not be reached, or the code had already expired. Nothing was saved - start again, or paste your keys in by hand.', 'captchaapi');

        printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($message));
    }

    /**
     * Connect is an onboarding step, so it only appears on a blank slate -
     * both key fields empty. Fill in one of them and you already have an
     * account, which means the other key is a copy away from your dashboard
     * and this flow has nothing to offer; you get a sign-in link instead.
     */
    public function render_button(): void
    {
        if ($this->options->is_configured()) {
            return;
        }

        if ($this->has_any_key()) {
            $this->render_sign_in_hint();

            return;
        }

        // Nothing to register, so nothing to connect. The description below
        // names the hostname the project would be locked to, and offering the
        // button with that name blank would promise something we cannot do.
        if ($this->site_host() === '') {
            return;
        }

        $url = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::START_ACTION),
            self::START_ACTION
        );

        ?>
        <p>
            <a href="<?php echo esc_url($url); ?>" class="button button-primary">
                <?php esc_html_e('Connect to captchaapi.eu', 'captchaapi'); ?>
            </a>
        </p>
        <p class="description">
            <?php
            printf(
                /* translators: %s: the site hostname that will be registered. */
                esc_html__('Creates your free account and a project for %s, then fills both keys in for you. Only that hostname and the address of this admin screen are sent; the keys come back over a direct server-to-server call.', 'captchaapi'),
                '<code>' . esc_html($this->site_host()) . '</code>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Either field filled counts, whether it came from the database or from a
     * wp-config constant. Connect writes both keys at once, so anything already
     * in place would be overwritten or shadowed.
     */
    private function has_any_key(): bool
    {
        return $this->options->site_key() !== '' || $this->options->secret_keys() !== [];
    }

    private function render_sign_in_hint(): void
    {
        ?>
        <p>
            <a href="<?php echo esc_url($this->options->connect_base_url() . '/login'); ?>"
               class="button" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('Sign in to captchaapi.eu', 'captchaapi'); ?>
            </a>
        </p>
        <p class="description">
            <?php esc_html_e('One key is already filled in, so this site is half set up. Open your project on captchaapi.eu and copy the missing key across.', 'captchaapi'); ?>
        </p>
        <?php
    }

    /**
     * Trade the code for the keys. `sslverify` is set explicitly because a
     * fair number of hosts disable it globally through a filter, and this
     * request carries a secret key on the way back.
     *
     * @return array{site_key: string, secret_key: string}|null
     */
    private function exchange(string $code, string $verifier, string $return): ?array
    {
        // connect_base_url(), never api_url(). A self-hosted proxy moves where
        // tokens are verified; it does not move where accounts and keys live,
        // and sending a one-time code there would hand a third party a
        // credential meant for captchaapi.eu. Same method start() used, so the
        // code is always redeemed at the host that issued it.
        $url = $this->options->connect_base_url() . '/api/v1/connect/exchange';

        // Setting sslverify in the args is not enough: WP_Http runs
        // `http_request_args` AFTER the caller, and hosts with a broken CA
        // bundle routinely ship a global filter that turns it off. This one
        // is scoped to this single URL and re-forces it last.
        $force_verify = static function (array $args, string $filtered_url) use ($url): array {
            if ($filtered_url === $url) {
                $args['sslverify'] = true;
            }

            return $args;
        };

        add_filter('http_request_args', $force_verify, PHP_INT_MAX, 2);

        $response = wp_remote_post(
            $url,
            [
                // Under the 30 s PHP default with room to spare. The verifier
                // has already been discarded by this point, so a request killed
                // by an execution-time limit costs the admin the whole attempt.
                'timeout'   => 10,
                'sslverify' => true,
                'headers'   => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'body'      => wp_json_encode([
                    'code'          => $code,
                    'code_verifier' => $verifier,
                    'return_url'    => $return,
                ]),
            ]
        );

        remove_filter('http_request_args', $force_verify, PHP_INT_MAX);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $raw = (string) wp_remote_retrieve_body($response);

        // Measured before decoding: a response big enough to matter is already
        // not ours, and json_decode() would be the thing that runs out of memory.
        if ($raw === '' || strlen($raw) > self::MAX_BODY_LENGTH) {
            return null;
        }

        $body = json_decode($raw, true);

        if (! is_array($body)) {
            return null;
        }

        $site_key   = self::clean_key($body['site_key'] ?? null);
        $secret_key = self::clean_key($body['secret_key'] ?? null);

        if ($site_key === '' || $secret_key === '') {
            return null;
        }

        return [
            'site_key'   => $site_key,
            'secret_key' => $secret_key,
        ];
    }

    /**
     * Keys arrive over the network, so they get the same treatment as a key
     * typed into the settings form - and a stricter type check than empty()
     * gives, which passes a JSON array straight through to a "Array" cast.
     *
     * A key that is anything but a clean string is refused rather than repaired.
     * Storing a mangled one is worse than storing none: Captchaapi_Gate closes
     * every surface outside ALWAYS_OPEN on `invalid_site_key`, so a corrupted
     * key would take out checkout and comments, while no key at all leaves the
     * plugin unconfigured and the forms untouched.
     *
     * @param mixed $value
     */
    private static function clean_key($value): string
    {
        if (! is_string($value)) {
            return '';
        }

        // Surrounding whitespace is a transport artefact, not corruption, so it
        // is trimmed before the comparison rather than counted against the key.
        $value = trim($value);

        if ($value === '' || strlen($value) > self::MAX_KEY_LENGTH) {
            return '';
        }

        // Refuse rather than repair: a real key is plain ASCII, so sanitizing
        // one is a no-op. If it changed anything, what came back is not a key,
        // and the sanitized remains of it would still be stored and still be
        // wrong - only now without the evidence that it was mangled.
        $clean = sanitize_text_field($value);

        return $clean === $value ? $clean : '';
    }

    /**
     * The hostname the widget will actually run on, in the form the server
     * compares against: browsers send punycode in `Origin`, and the allow-list
     * is an exact match.
     *
     * `idn_to_ascii()` needs ext-intl, which plenty of shared hosts omit, so
     * the Requests encoder bundled with WordPress is the fallback. Its class
     * was renamed in WordPress 6.2 and this plugin still supports 6.0, hence
     * both names - the modern one first, because the old one resolves through
     * a deprecation shim on current installs.
     */
    private function site_host(): string
    {
        $parts = wp_parse_url(home_url());
        $host  = isset($parts['host']) ? strtolower((string) $parts['host']) : '';

        if ($host === '') {
            return '';
        }

        $ascii = $this->to_punycode($host);

        if (! empty($parts['port'])) {
            $ascii .= ':' . (int) $parts['port'];
        }

        return $ascii;
    }

    private function to_punycode(string $host): string
    {
        if (function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46')) {
            // The default variant is still the deprecated 2003 one on PHP 7.4;
            // it only changed in PHP 8.0, so pass it explicitly.
            $converted = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        foreach (['WpOrg\\Requests\\IdnaEncoder', 'Requests_IDNAEncoder'] as $encoder) {
            if (! class_exists($encoder)) {
                continue;
            }

            try {
                return (string) call_user_func([$encoder, 'encode'], $host);
            } catch (Exception $e) {
                break;
            }
        }

        // Nothing available: hand over the host as typed. The server rejects
        // anything non-ASCII with a readable message, which beats a fatal.
        return $host;
    }

    private function return_url(): string
    {
        return admin_url('options-general.php?page=' . Captchaapi_Settings::PAGE);
    }

    /**
     * @param array{state: string, verifier: string, return: string, created: int} $data
     */
    private function remember(array $data): void
    {
        // An abandoned attempt (tab closed, declined on the other side) never
        // reaches the return handler, so nothing would otherwise clear its
        // verifier. Sweep expired entries whenever a new one is written.
        $all = array_filter(
            $this->all_pending(),
            static function ($entry) {
                return is_array($entry)
                    && isset($entry['created'])
                    && time() - (int) $entry['created'] <= self::PENDING_TTL;
            }
        );

        $all[get_current_user_id()] = $data;

        update_option(self::PENDING_OPTION, $all, false);
    }

    /**
     * An expired entry is dropped here rather than only ignored. The sweep in
     * remember() runs when someone starts a new connect, which an admin who
     * gave up halfway never does - and leaving a spent verifier in the database
     * for that reason is the thing this class is careful about everywhere else.
     *
     * @return array{state: string, verifier: string, return: string, created: int}|null
     */
    private function pending(): ?array
    {
        $all  = $this->all_pending();
        $mine = isset($all[get_current_user_id()]) ? $all[get_current_user_id()] : null;

        if (! is_array($mine) || ! isset($mine['state'], $mine['verifier'], $mine['return'], $mine['created'])) {
            return null;
        }

        if (time() - (int) $mine['created'] > self::PENDING_TTL) {
            $this->forget();

            return null;
        }

        return $mine;
    }

    private function forget(): void
    {
        $all = $this->all_pending();
        unset($all[get_current_user_id()]);

        if ($all === []) {
            delete_option(self::PENDING_OPTION);

            return;
        }

        update_option(self::PENDING_OPTION, $all, false);
    }

    /**
     * @return array<int, mixed>
     */
    private function all_pending(): array
    {
        $stored = get_option(self::PENDING_OPTION, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * Leave the code out of the address bar, the browser history and the
     * host's access log, and stop a refresh from replaying the exchange.
     */
    private function finish(string $result): void
    {
        wp_safe_redirect(add_query_arg(self::RESULT_ARG, $result, $this->return_url()));
        exit;
    }

    private static function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
