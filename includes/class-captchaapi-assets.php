<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Loads the widget and points it at the forms we protect.
 *
 * The core forms (login, registration, lost password, comments) submit with a
 * native POST, so the widget runs in its default submit mode. WordPress gives no
 * server-side hook to add an attribute to those <form> tags, so a small marker
 * script tags them with data-captcha in the browser. The marker is a dependency
 * of the widget, which guarantees it runs first and the form is marked before
 * the widget scans the page.
 *
 * Contact Form 7 submits over its own AJAX and cannot run in submit mode, so it
 * gets a separate script built around window.captchaapi.solve().
 */
class Captchaapi_Assets
{
    private Captchaapi_Options $options;

    public function __construct(Captchaapi_Options $options)
    {
        $this->options = $options;
    }

    public function boot(): void
    {
        add_action('login_enqueue_scripts', [$this, 'enqueue_login']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend']);
        add_filter('script_loader_tag', [$this, 'defer_widget'], 10, 2);
    }

    public function enqueue_login(): void
    {
        if (! $this->options->is_configured()) {
            return;
        }

        $forms = [];
        if ($this->options->protects('login')) {
            $forms[] = ['selector' => '#loginform'];
        }
        if ($this->options->protects('register')) {
            $forms[] = ['selector' => '#registerform'];
        }
        if ($this->options->protects('lost_password')) {
            $forms[] = ['selector' => '#lostpasswordform'];
        }

        if ($forms === []) {
            return;
        }

        $this->enqueue_widget($forms, true);
    }

    public function enqueue_frontend(): void
    {
        if (! $this->options->is_configured()) {
            return;
        }

        $forms = [];
        if ($this->options->protects('comments') && is_singular() && comments_open()) {
            $forms[] = ['selector' => '#commentform'];
        }

        $needs_cf7 = $this->options->protects('cf7') && Captchaapi_Contact_Form_7::is_active();

        if ($forms === [] && ! $needs_cf7) {
            return;
        }

        $this->enqueue_widget($forms, false);

        if ($needs_cf7) {
            wp_enqueue_script(
                'captchaapi-cf7',
                CAPTCHAAPI_PLUGIN_URL . 'assets/js/captchaapi-cf7.js',
                ['captchaapi'],
                CAPTCHAAPI_VERSION,
                true
            );
            wp_add_inline_script(
                'captchaapi-cf7',
                'window.captchaapiCf7 = ' . wp_json_encode([
                    'unavailable' => __('Verification is temporarily unavailable. Please try again.', 'captchaapi'),
                ]) . ';',
                'before'
            );
        }
    }

    /**
     * @param array<int, array{selector: string}> $marker_forms
     */
    private function enqueue_widget(array $marker_forms, bool $in_head): void
    {
        $in_footer = ! $in_head;
        $deps      = [];

        if ($marker_forms !== []) {
            $this->register_marker($marker_forms, $in_footer);
            $deps[] = 'captchaapi-forms';
        }

        if (! wp_script_is('captchaapi', 'registered')) {
            wp_register_script('captchaapi', $this->options->widget_url(), $deps, null, $in_footer);
            wp_add_inline_script('captchaapi', $this->config_script(), 'before');
        }

        wp_enqueue_script('captchaapi');
    }

    /**
     * @param array<int, array{selector: string}> $marker_forms
     */
    private function register_marker(array $marker_forms, bool $in_footer): void
    {
        if (wp_script_is('captchaapi-forms', 'registered')) {
            return;
        }

        wp_register_script(
            'captchaapi-forms',
            CAPTCHAAPI_PLUGIN_URL . 'assets/js/captchaapi-forms.js',
            [],
            CAPTCHAAPI_VERSION,
            $in_footer
        );
        wp_add_inline_script(
            'captchaapi-forms',
            'window.captchaapiForms = ' . wp_json_encode($marker_forms) . ';',
            'before'
        );
    }

    private function config_script(): string
    {
        return sprintf(
            'window.CAPTCHA_SITE_KEY=%s;window.CAPTCHA_BASE_URL=%s;window.CAPTCHA_LOCALE=%s;',
            wp_json_encode($this->options->site_key()),
            wp_json_encode($this->options->api_url()),
            wp_json_encode($this->locale())
        );
    }

    /**
     * @param string $tag
     * @param string $handle
     */
    public function defer_widget($tag, $handle): string
    {
        if ($handle === 'captchaapi' && strpos($tag, ' defer') === false) {
            $tag = str_replace(' src=', ' defer src=', $tag);
        }

        return $tag;
    }

    private function locale(): string
    {
        return strtolower(substr(get_locale(), 0, 2));
    }
}
