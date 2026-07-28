<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Settings screen under Settings -> captchaapi.eu. Stores everything in a single
 * option array and leans on the Settings API for the save round-trip, nonce, and
 * capability checks.
 */
class Captchaapi_Settings
{
    const PAGE = 'captchaapi';

    const GROUP = 'captchaapi';

    private Captchaapi_Options $options;

    public function __construct(Captchaapi_Options $options)
    {
        $this->options = $options;
    }

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'add_page']);
        add_action('admin_init', [$this, 'register']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
        add_action('wp_ajax_captchaapi_test', [$this, 'ajax_test']);
        add_filter(
            'plugin_action_links_' . plugin_basename(CAPTCHAAPI_PLUGIN_FILE),
            [$this, 'action_links']
        );
    }

    /**
     * @param string $hook
     */
    public function enqueue_admin($hook): void
    {
        if ($hook !== 'settings_page_' . self::PAGE) {
            return;
        }

        wp_enqueue_style(
            'captchaapi-admin',
            CAPTCHAAPI_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CAPTCHAAPI_VERSION
        );

        wp_enqueue_script(
            'captchaapi-admin',
            CAPTCHAAPI_PLUGIN_URL . 'assets/js/captchaapi-admin.js',
            [],
            CAPTCHAAPI_VERSION,
            true
        );
        wp_add_inline_script(
            'captchaapi-admin',
            'window.captchaapiAdmin = ' . wp_json_encode([
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('captchaapi_test'),
                'testing' => __('Testing...', 'captchaapi'),
                'failure' => __('Test failed.', 'captchaapi'),
            ]) . ';',
            'before'
        );
    }

    public function ajax_test(): void
    {
        check_ajax_referer('captchaapi_test', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Not allowed.', 'captchaapi')], 403);
        }

        $site_key    = $this->options->site_key();
        $secret_keys = $this->options->secret_keys();

        if ($site_key === '' || $secret_keys === []) {
            wp_send_json_error(['message' => __('Add a site key and a secret key first.', 'captchaapi')]);
        }

        // This check only pings the service for reachability, not the secret, so
        // the most it can catch is keys pasted into the wrong field.
        if (strpos($site_key, 'sk_') === 0 || strpos($secret_keys[0], 'pk_') === 0) {
            wp_send_json_error(['message' => __('The keys look swapped: the site key should start with pk_ and the secret key with sk_.', 'captchaapi')]);
        }

        // Ask the service for a challenge with this site key. An unknown key is
        // rejected with invalid_site_key before any other check, so this is what
        // actually proves the site key is real. The Origin header carries this
        // site's domain so a project with a domain allowlist validates the same
        // way it would for a real browser request.
        $response = wp_remote_post($this->options->api_url() . '/captcha/challenge', [
            'timeout' => 8,
            'headers' => [
                'Content-Type' => 'application/json',
                'Origin'       => $this->site_origin(),
            ],
            'body'    => wp_json_encode(['site_key' => $site_key]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => __('Could not reach the service. Check the API base URL and that captchaapi.eu is up.', 'captchaapi')]);
        }

        if ((int) wp_remote_retrieve_response_code($response) === 200) {
            wp_send_json_success(['message' => __('Connected. Your site key is valid.', 'captchaapi')]);
        }

        $body       = json_decode((string) wp_remote_retrieve_body($response), true);
        $error_code = (is_array($body) && isset($body['error_code'])) ? (string) $body['error_code'] : '';

        wp_send_json_error(['message' => $this->test_error_message($error_code)]);
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

    private function test_error_message(string $error_code): string
    {
        switch ($error_code) {
            case 'invalid_site_key':
                return __('Site key not recognized. Check that you copied the site key correctly.', 'captchaapi');
            case 'domain_not_allowed':
                return __('The site key is valid, but this site\'s domain is not in the project\'s allowed domains.', 'captchaapi');
            case 'free_tier_limit_reached':
                return __('The free tier limit for this billing period has been reached.', 'captchaapi');
            case 'account_suspended':
                return __('The account is suspended over billing.', 'captchaapi');
            case 'account_deactivated':
                return __('The account is deactivated.', 'captchaapi');
            case 'project_inactive':
                return __('The project is inactive.', 'captchaapi');
            case 'rate_limited':
                return __('Too many requests right now. The site key is valid; try again shortly.', 'captchaapi');
            case '':
                return __('The service returned an unexpected response.', 'captchaapi');
            default:
                /* translators: %s: error code returned by the service. */
                return sprintf(__('The service rejected the request (%s).', 'captchaapi'), $error_code);
        }
    }

    public function add_page(): void
    {
        add_options_page(
            'captchaapi.eu',
            'captchaapi.eu',
            'manage_options',
            self::PAGE,
            [$this, 'render']
        );
    }

    public function register(): void
    {
        register_setting(self::GROUP, Captchaapi_Options::OPTION_KEY, [
            'sanitize_callback' => [$this, 'sanitize'],
            'default'           => $this->options->defaults(),
        ]);
    }

    /**
     * @param mixed $input
     *
     * @return array<string, mixed>
     */
    public function sanitize($input): array
    {
        $input   = is_array($input) ? $input : [];
        $current = $this->options->all();
        $output  = $this->options->defaults();

        // A value managed by a wp-config constant is rendered disabled (or omitted),
        // so the form never submits it. Keep the stored value rather than wiping it,
        // otherwise removing the constant later would leave the database empty.
        $output['site_key'] = (defined('CAPTCHAAPI_SITE_KEY') && CAPTCHAAPI_SITE_KEY)
            ? (string) $current['site_key']
            : (isset($input['site_key']) ? sanitize_text_field($input['site_key']) : '');

        $output['secret_key'] = $this->options->secret_key_is_constant()
            ? (string) $current['secret_key']
            : (isset($input['secret_key']) ? sanitize_text_field($input['secret_key']) : '');

        if (defined('CAPTCHAAPI_BASE_URL') && CAPTCHAAPI_BASE_URL) {
            $output['base_url'] = (string) $current['base_url'];
        } else {
            $base               = isset($input['base_url']) ? esc_url_raw(trim((string) $input['base_url'])) : '';
            $output['base_url'] = $base !== '' ? untrailingslashit($base) : Captchaapi_Options::DEFAULT_BASE_URL;
        }

        $toggles = [
            'protect_login', 'protect_register', 'protect_lost_password', 'protect_comments',
            'protect_cf7', 'protect_woo_checkout', 'protect_wpforms', 'protect_fluentform',
            'protect_formidable', 'protect_forminator', 'protect_gravityforms', 'protect_elementor_forms',
        ];

        foreach ($toggles as $toggle) {
            $output[$toggle] = ! empty($input[$toggle]);
        }

        $output['failsafe'] = ! empty($input['failsafe']);

        return $output;
    }

    /**
     * @param array<int, string> $links
     *
     * @return array<int, string>
     */
    public function action_links($links): array
    {
        $settings = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=' . self::PAGE)),
            esc_html__('Settings', 'captchaapi')
        );

        array_unshift($links, $settings);

        if (! $this->options->is_configured()) {
            array_unshift($links, sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" style="color:#d63638;font-weight:600;">%s</a>',
                esc_url($this->signup_url()),
                esc_html__('Get your free keys', 'captchaapi')
            ));
        }

        return $links;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $values            = $this->options->all();
        $secret_is_constant = $this->options->secret_key_is_constant();
        $name               = Captchaapi_Options::OPTION_KEY;
        ?>
        <div class="wrap captchaapi-settings">
            <h1>captchaapi.eu</h1>

            <?php $this->render_status(); ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <h2 class="title"><?php esc_html_e('Account keys', 'captchaapi'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="captchaapi-site-key"><?php esc_html_e('Site key', 'captchaapi'); ?></label></th>
                        <td>
                            <input type="text" id="captchaapi-site-key" class="regular-text"
                                name="<?php echo esc_attr($name); ?>[site_key]"
                                value="<?php echo esc_attr($this->options->site_key()); ?>"
                                placeholder="pk_live_..."
                                <?php disabled(defined('CAPTCHAAPI_SITE_KEY') && CAPTCHAAPI_SITE_KEY); ?>>
                            <p class="description">
                                <?php esc_html_e('The public site key from your project dashboard. Starts with pk_live_ and is safe to expose in the page.', 'captchaapi'); ?>
                                <?php
                                printf(
                                    /* translators: %s: link to create a free account. */
                                    ' ' . esc_html__('Don\'t have one yet? %s.', 'captchaapi'),
                                    '<a href="' . esc_url($this->signup_url()) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Get your free keys', 'captchaapi') . '</a>'
                                );
                                ?>
                                <?php if (defined('CAPTCHAAPI_SITE_KEY') && CAPTCHAAPI_SITE_KEY) : ?>
                                    <br><?php esc_html_e('Currently set by the CAPTCHAAPI_SITE_KEY constant in wp-config.php.', 'captchaapi'); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="captchaapi-secret-key"><?php esc_html_e('Secret key', 'captchaapi'); ?></label></th>
                        <td>
                            <?php if ($secret_is_constant) : ?>
                                <p class="description">
                                    <?php esc_html_e('Set by the CAPTCHAAPI_SECRET_KEYS constant in wp-config.php. Clear the constant to manage it here.', 'captchaapi'); ?>
                                </p>
                            <?php else : ?>
                                <input type="password" id="captchaapi-secret-key" class="regular-text"
                                    name="<?php echo esc_attr($name); ?>[secret_key]"
                                    value="<?php echo esc_attr((string) $values['secret_key']); ?>"
                                    placeholder="sk_live_..."
                                    autocomplete="new-password">
                                <p class="captchaapi-secret-warning">
                                    <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                    <strong><?php esc_html_e('Keep this secret.', 'captchaapi'); ?></strong>
                                    <?php esc_html_e('It belongs on the server only. Never put it in templates, JavaScript, or any public code. Anyone who has it can forge verifications.', 'captchaapi'); ?>
                                </p>
                                <p class="description">
                                    <?php esc_html_e('Starts with sk_live_. During a key rotation, enter both keys separated by a comma.', 'captchaapi'); ?>
                                    <br><?php esc_html_e('For a stricter setup, define CAPTCHAAPI_SECRET_KEYS in wp-config.php instead and leave this blank.', 'captchaapi'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Test connection', 'captchaapi'); ?></th>
                        <td>
                            <button type="button" class="button" id="captchaapi-test"><?php esc_html_e('Test connection', 'captchaapi'); ?></button>
                            <span id="captchaapi-test-result" class="captchaapi-test-result" role="status" aria-live="polite"></span>
                            <p class="description">
                                <?php esc_html_e('Checks that captchaapi.eu is reachable and your site key is valid. Save your keys first.', 'captchaapi'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('Protected forms', 'captchaapi'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><span class="screen-reader-text"><?php esc_html_e('Protected forms', 'captchaapi'); ?></span></th>
                        <td>
                            <fieldset>
                                <?php
                                $this->checkbox($name, 'protect_login', __('Login', 'captchaapi'), ! empty($values['protect_login']));
                                $this->checkbox($name, 'protect_register', __('Registration', 'captchaapi'), ! empty($values['protect_register']));
                                $this->checkbox($name, 'protect_lost_password', __('Lost password', 'captchaapi'), ! empty($values['protect_lost_password']));
                                $this->checkbox($name, 'protect_comments', __('Comments', 'captchaapi'), ! empty($values['protect_comments']));

                                if (Captchaapi_Contact_Form_7::is_active()) {
                                    $this->checkbox($name, 'protect_cf7', 'Contact Form 7', ! empty($values['protect_cf7']));
                                }

                                if (Captchaapi_WooCommerce::is_active()) {
                                    $this->checkbox($name, 'protect_woo_checkout', __('WooCommerce checkout', 'captchaapi'), ! empty($values['protect_woo_checkout']));
                                }

                                if (Captchaapi_WPForms::is_active()) {
                                    $this->checkbox($name, 'protect_wpforms', 'WPForms', ! empty($values['protect_wpforms']));
                                }
                                if (Captchaapi_Fluent_Forms::is_active()) {
                                    $this->checkbox($name, 'protect_fluentform', 'Fluent Forms', ! empty($values['protect_fluentform']));
                                }
                                if (Captchaapi_Formidable::is_active()) {
                                    $this->checkbox($name, 'protect_formidable', 'Formidable Forms', ! empty($values['protect_formidable']));
                                }
                                if (Captchaapi_Forminator::is_active()) {
                                    $this->checkbox($name, 'protect_forminator', 'Forminator', ! empty($values['protect_forminator']));
                                }
                                if (Captchaapi_Gravity_Forms::is_active()) {
                                    $this->checkbox($name, 'protect_gravityforms', 'Gravity Forms', ! empty($values['protect_gravityforms']));
                                }
                                if (Captchaapi_Elementor_Forms::is_active()) {
                                    $this->checkbox($name, 'protect_elementor_forms', 'Elementor Pro Forms', ! empty($values['protect_elementor_forms']));
                                }
                                ?>
                                <?php if (Captchaapi_WooCommerce::is_active()) : ?>
                                    <p class="description">
                                        <?php esc_html_e('WooCommerce login, registration, and lost-password forms follow the matching options above.', 'captchaapi'); ?>
                                    </p>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('Behavior', 'captchaapi'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Failsafe mode', 'captchaapi'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr($name); ?>[failsafe]" value="1" <?php checked(! empty($values['failsafe'])); ?>>
                                    <?php esc_html_e('Let submissions through when captchaapi.eu is unreachable', 'captchaapi'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('During an outage the verify request cannot reach captchaapi.eu, so strict mode would block every form. With this on, forms stay usable and protection resumes automatically once the service is back. Leave off for strict, fail-closed protection.', 'captchaapi'); ?>
                                </p>
                                <p class="description">
                                    <?php esc_html_e('This setting covers outages on our side only. When the account itself cannot issue challenges - the free tier is used up, the account is suspended, the project is inactive - protected forms are always rejected until you resolve it. Logging in and resetting a password are the exception: they stay open so you can never be shut out of your own site.', 'captchaapi'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('Advanced', 'captchaapi'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="captchaapi-base-url"><?php esc_html_e('API base URL', 'captchaapi'); ?></label></th>
                        <td>
                            <input type="url" id="captchaapi-base-url" class="regular-text"
                                name="<?php echo esc_attr($name); ?>[base_url]"
                                value="<?php echo esc_attr((string) $values['base_url']); ?>"
                                <?php disabled(defined('CAPTCHAAPI_BASE_URL') && CAPTCHAAPI_BASE_URL); ?>>
                            <p class="description">
                                <?php esc_html_e('Leave as the default unless you self-host or proxy the API.', 'captchaapi'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function render_status(): void
    {
        if ($this->options->is_configured()) {
            printf(
                '<div class="notice notice-success inline captchaapi-status"><p>%s</p></div>',
                esc_html__('Keys are set. Your selected forms are protected.', 'captchaapi')
            );

            return;
        }

        printf(
            '<div class="notice notice-warning inline captchaapi-status">'
                . '<p>%1$s</p>'
                . '<p><a class="button button-primary" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></p>'
                . '</div>',
            esc_html__('No keys yet, so no forms are protected. Create a free account to get your site key and secret key, then paste them in below.', 'captchaapi'),
            esc_url($this->signup_url()),
            esc_html__('Get your free keys', 'captchaapi')
        );
    }

    /**
     * Where a new user creates an account and generates keys. Built from the
     * canonical host rather than the configurable API base URL, so a self-hosted
     * proxy entered in that field never sends new users to a page that does not exist.
     */
    private function signup_url(): string
    {
        return Captchaapi_Options::DEFAULT_BASE_URL . '/register';
    }

    private function checkbox(string $name, string $key, string $label, bool $checked): void
    {
        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label><br>',
            esc_attr($name),
            esc_attr($key),
            checked($checked, true, false),
            esc_html($label)
        );
    }
}
