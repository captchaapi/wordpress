<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Reads and writes the plugin configuration.
 *
 * Keys and secrets can be supplied two ways. A wp-config.php constant always
 * wins over the stored option, which keeps the secret key out of the database
 * on hosts that care about it:
 *
 *   define( 'CAPTCHAAPI_SITE_KEY', 'pk_live_...' );
 *   define( 'CAPTCHAAPI_SECRET_KEYS', 'sk_live_current,sk_live_pending' );
 */
class Captchaapi_Options
{
    const OPTION_KEY = 'captchaapi_options';

    const DEFAULT_BASE_URL = 'https://captchaapi.eu';

    /**
     * What the visitor sees under a protected form.
     *
     * BADGE_STATUS is a plain line of text reporting whether the form is
     * protected - no brand, no link. It is on by default because without it a
     * cookieless, puzzle-free captcha leaves nothing at all to see, and neither
     * the visitor nor the site owner can tell it is working.
     *
     * BADGE_BRANDED adds our logo and a link, which makes it a credit display.
     * WordPress.org requires those to be opt-in, so it is never the default.
     */
    const BADGE_NONE = 'none';

    const BADGE_STATUS = 'status';

    const BADGE_BRANDED = 'branded';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = get_option(self::OPTION_KEY, []);

        return array_merge($this->defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'site_key'              => '',
            'secret_key'            => '',
            'base_url'              => self::DEFAULT_BASE_URL,
            'badge'                 => self::BADGE_STATUS,
            'protect_login'         => true,
            'protect_register'      => true,
            'protect_lost_password' => true,
            'protect_comments'      => true,
            'protect_cf7'           => true,
            'protect_woo_checkout'  => true,
            'protect_wpforms'       => true,
            'protect_fluentform'    => true,
            'protect_formidable'    => true,
            'protect_forminator'    => true,
            'protect_gravityforms'  => true,
            'protect_elementor_forms' => true,
            'failsafe'              => false,
        ];
    }

    public function site_key(): string
    {
        if (defined('CAPTCHAAPI_SITE_KEY') && CAPTCHAAPI_SITE_KEY) {
            return (string) CAPTCHAAPI_SITE_KEY;
        }

        return (string) $this->all()['site_key'];
    }

    /**
     * The secret key list, supporting comma-separated rotation (current + pending).
     *
     * @return array<int, string>
     */
    public function secret_keys(): array
    {
        $raw = $this->secret_key_is_constant()
            ? (string) CAPTCHAAPI_SECRET_KEYS
            : (string) $this->all()['secret_key'];

        return array_values(array_filter(array_map('trim', explode(',', $raw)), function ($key) {
            return $key !== '';
        }));
    }

    public function secret_key_is_constant(): bool
    {
        return defined('CAPTCHAAPI_SECRET_KEYS') && CAPTCHAAPI_SECRET_KEYS;
    }

    /**
     * The secret sent as the Bearer token on verify. One key, not the list:
     * the server accepts both the current and the pending key while a rotation
     * is staged, so sending the current one (the first) has no swap-over window.
     */
    public function current_secret_key(): string
    {
        $keys = $this->secret_keys();

        return $keys[0] ?? '';
    }

    /**
     * Base origin for the widget script and the API. The widget is loaded from
     * "{base}/captcha.js" and talks to "{base}/api/v1".
     */
    public function base_url(): string
    {
        if (defined('CAPTCHAAPI_BASE_URL') && CAPTCHAAPI_BASE_URL) {
            return untrailingslashit((string) CAPTCHAAPI_BASE_URL);
        }

        $base = (string) $this->all()['base_url'];

        return untrailingslashit($base !== '' ? $base : self::DEFAULT_BASE_URL);
    }

    public function widget_url(): string
    {
        return $this->base_url() . '/captcha.js';
    }

    public function api_url(): string
    {
        return $this->base_url() . '/api/v1';
    }

    public function is_configured(): bool
    {
        return $this->site_key() !== '' && $this->secret_keys() !== [];
    }

    public function failsafe(): bool
    {
        return ! empty($this->all()['failsafe']);
    }

    /**
     * One of the BADGE_* constants. An unrecognised stored value falls back to
     * the status line rather than to the branded one, so a corrupted option can
     * never turn a credit display on by itself.
     */
    public function badge(): string
    {
        $badge = (string) ($this->all()['badge'] ?? self::BADGE_STATUS);

        return in_array($badge, [self::BADGE_NONE, self::BADGE_STATUS, self::BADGE_BRANDED], true)
            ? $badge
            : self::BADGE_STATUS;
    }

    public function protects(string $surface): bool
    {
        $key = 'protect_' . $surface;
        $all = $this->all();

        return ! empty($all[$key]);
    }
}
