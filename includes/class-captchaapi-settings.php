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
    }

    public function add_page(): void
    {
        add_options_page(
            __('captchaapi.eu', 'captchaapi'),
            __('captchaapi.eu', 'captchaapi'),
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

        foreach (['protect_login', 'protect_register', 'protect_lost_password', 'protect_comments', 'protect_cf7'] as $toggle) {
            $output[$toggle] = ! empty($input[$toggle]);
        }

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
            <h1><?php esc_html_e('captchaapi.eu', 'captchaapi'); ?></h1>

            <?php $this->render_status(); ?>

            <form method="post" action="options.php">
                <?php settings_fields(self::GROUP); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="captchaapi-site-key"><?php esc_html_e('Site key', 'captchaapi'); ?></label></th>
                        <td>
                            <input type="text" id="captchaapi-site-key" class="regular-text"
                                name="<?php echo esc_attr($name); ?>[site_key]"
                                value="<?php echo esc_attr($this->options->site_key()); ?>"
                                <?php disabled(defined('CAPTCHAAPI_SITE_KEY') && CAPTCHAAPI_SITE_KEY); ?>>
                            <p class="description">
                                <?php esc_html_e('The public site key from your project dashboard. Safe to expose in the page.', 'captchaapi'); ?>
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
                                    autocomplete="new-password">
                                <p class="description">
                                    <?php esc_html_e('The private secret key. During a key rotation, enter both keys separated by a comma.', 'captchaapi'); ?>
                                    <br><?php esc_html_e('For a stricter setup, define CAPTCHAAPI_SECRET_KEYS in wp-config.php instead and leave this blank.', 'captchaapi'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Protected forms', 'captchaapi'); ?></th>
                        <td>
                            <fieldset>
                                <?php
                                $this->checkbox($name, 'protect_login', __('Login', 'captchaapi'), ! empty($values['protect_login']));
                                $this->checkbox($name, 'protect_register', __('Registration', 'captchaapi'), ! empty($values['protect_register']));
                                $this->checkbox($name, 'protect_lost_password', __('Lost password', 'captchaapi'), ! empty($values['protect_lost_password']));
                                $this->checkbox($name, 'protect_comments', __('Comments', 'captchaapi'), ! empty($values['protect_comments']));

                                if (Captchaapi_Contact_Form_7::is_active()) {
                                    $this->checkbox($name, 'protect_cf7', __('Contact Form 7', 'captchaapi'), ! empty($values['protect_cf7']));
                                }
                                ?>
                            </fieldset>
                        </td>
                    </tr>
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
            $class   = 'notice notice-success inline';
            $message = __('Keys are set. Your selected forms are protected.', 'captchaapi');
        } else {
            $class   = 'notice notice-warning inline';
            $message = __('Add a site key and a secret key to start protecting forms.', 'captchaapi');
        }

        printf(
            '<div class="%1$s captchaapi-status"><p>%2$s</p></div>',
            esc_attr($class),
            esc_html($message)
        );
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
