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

        $this->enqueue_widget($forms, wp_list_pluck($forms, 'selector'), true);
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

        if (Captchaapi_WooCommerce::is_active() && function_exists('is_account_page') && is_account_page()) {
            if ($this->options->protects('login')) {
                $forms[] = ['selector' => '.woocommerce-form-login'];
            }
            if ($this->options->protects('register')) {
                $forms[] = ['selector' => '.woocommerce-form-register'];
            }
            if ($this->options->protects('lost_password')) {
                $forms[] = ['selector' => '.woocommerce-ResetPassword'];
            }
        }

        $needs_cf7 = $this->options->protects('cf7') && Captchaapi_Contact_Form_7::is_active();

        $needs_woo_checkout = $this->options->protects('woo_checkout')
            && Captchaapi_WooCommerce::is_active()
            && function_exists('is_checkout') && is_checkout();

        $ajax_selectors = $this->ajax_form_selectors();

        if ($forms === [] && ! $needs_cf7 && ! $needs_woo_checkout && $ajax_selectors === []) {
            return;
        }

        // The selectors handed over here are the ones the badge script attaches
        // to by itself, which is only ever the marker-driven forms. Contact
        // Form 7, the checkout, and the form plugins report through
        // solve({ form }), so their own scripts attach a badge once the widget
        // has loaded and they can check it is a build that reports at all.
        $this->enqueue_widget($forms, wp_list_pluck($forms, 'selector'), false, true);

        if ($ajax_selectors !== []) {
            wp_enqueue_script(
                'captchaapi-ajax',
                CAPTCHAAPI_PLUGIN_URL . 'assets/js/captchaapi-ajax.js',
                ['captchaapi'],
                CAPTCHAAPI_VERSION,
                true
            );
            wp_add_inline_script(
                'captchaapi-ajax',
                'window.captchaapiAjax = ' . wp_json_encode([
                    'selectors'   => $ajax_selectors,
                    'unavailable' => __('Verification is temporarily unavailable. Please try again.', 'captchaapi'),
                ]) . ';',
                'before'
            );
        }

        if ($needs_woo_checkout) {
            wp_enqueue_script(
                'captchaapi-woocommerce',
                CAPTCHAAPI_PLUGIN_URL . 'assets/js/captchaapi-woocommerce.js',
                ['captchaapi'],
                CAPTCHAAPI_VERSION,
                true
            );
            wp_add_inline_script(
                'captchaapi-woocommerce',
                'window.captchaapiWoo = ' . wp_json_encode([
                    'unavailable' => __('Verification is temporarily unavailable. Please try again.', 'captchaapi'),
                ]) . ';',
                'before'
            );
        }

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
     * CSS selectors for the form plugins that submit over their own AJAX and are
     * both active and enabled. The generic gate in captchaapi-ajax.js attaches an
     * attestation to any form matching one of these.
     *
     * @return array<int, string>
     */
    private function ajax_form_selectors(): array
    {
        $integrations = [
            ['toggle' => 'wpforms', 'class' => 'Captchaapi_WPForms', 'selector' => '.wpforms-form'],
            ['toggle' => 'fluentform', 'class' => 'Captchaapi_Fluent_Forms', 'selector' => '.frm-fluent-form'],
            ['toggle' => 'formidable', 'class' => 'Captchaapi_Formidable', 'selector' => '.frm-show-form'],
            ['toggle' => 'forminator', 'class' => 'Captchaapi_Forminator', 'selector' => 'form.forminator-custom-form'],
            ['toggle' => 'gravityforms', 'class' => 'Captchaapi_Gravity_Forms', 'selector' => '.gform_wrapper form'],
            ['toggle' => 'elementor_forms', 'class' => 'Captchaapi_Elementor_Forms', 'selector' => '.elementor-form'],
        ];

        $selectors = [];
        foreach ($integrations as $integration) {
            if ($this->options->protects($integration['toggle']) && call_user_func([$integration['class'], 'is_active'])) {
                $selectors[] = $integration['selector'];
            }
        }

        return $selectors;
    }

    /**
     * @param array<int, array{selector: string}> $marker_forms    Forms the marker tags with data-captcha.
     * @param array<int, string>                  $badge_selectors Forms the badge script attaches to on its own.
     * @param bool                                $badge_on_demand Whether an integration script on this page will
     *                                                             attach badges of its own, so the badge script is
     *                                                             needed even when it has no selectors to work from.
     */
    private function enqueue_widget(
        array $marker_forms,
        array $badge_selectors,
        bool $in_head,
        bool $badge_on_demand = false
    ): void {
        $in_footer = ! $in_head;
        $deps      = [];

        if ($marker_forms !== []) {
            $this->register_marker($marker_forms, $in_footer);
            $deps[] = 'captchaapi-forms';
        }

        if ($this->register_badge($badge_selectors, $in_footer, $badge_on_demand)) {
            $deps[] = 'captchaapi-badge';
        }

        if (! wp_script_is('captchaapi', 'registered')) {
            // No version on the widget. It is the service's file, released on
            // the service's schedule; stamping it with ours would pin every
            // browser to whatever build shipped alongside this plugin version
            // and hide the next widget deploy until the plugin releases again.
            // Its own ETag and Last-Modified decide when a browser refetches.
            // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Deliberate: the service versions this file, we do not.
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

    /**
     * Registers the badge as a dependency of the widget, so the status element
     * exists before the widget starts reporting into it.
     *
     * @param array<int, string> $selectors
     *
     * @return bool Whether the widget should depend on it.
     */
    private function register_badge(array $selectors, bool $in_footer, bool $on_demand = false): bool
    {
        if ($this->options->badge() === Captchaapi_Options::BADGE_NONE) {
            return false;
        }

        if ($selectors === [] && ! $on_demand) {
            return false;
        }

        if (wp_script_is('captchaapi-badge', 'registered')) {
            return true;
        }

        wp_register_script(
            'captchaapi-badge',
            CAPTCHAAPI_PLUGIN_URL . 'assets/js/captchaapi-badge.js',
            [],
            CAPTCHAAPI_VERSION,
            $in_footer
        );
        wp_add_inline_script(
            'captchaapi-badge',
            'window.captchaapiBadge = ' . wp_json_encode($this->badge_config($selectors)) . ';',
            'before'
        );

        wp_enqueue_style(
            'captchaapi-badge',
            CAPTCHAAPI_PLUGIN_URL . 'assets/css/badge.css',
            [],
            CAPTCHAAPI_VERSION
        );

        return true;
    }

    /**
     * @param array<int, string> $selectors
     *
     * @return array<string, mixed>
     */
    private function badge_config(array $selectors): array
    {
        $badge = $this->options->badge();

        if ($badge === Captchaapi_Options::BADGE_STATUS) {
            return [
                'mode'      => Captchaapi_Options::BADGE_STATUS,
                'selectors' => $selectors,
            ];
        }

        return [
            'mode'      => Captchaapi_Options::BADGE_BRANDED,
            'selectors' => $selectors,
            'href'      => $this->options->site_url(),
            'logo'      => CAPTCHAAPI_PLUGIN_URL . 'assets/img/captchaapi-logo.svg',
            'label'     => __('Powered by', 'captchaapi'),
            /* translators: %s: captchaapi.eu, the service name. */
            'aria'      => sprintf(__('Powered by %s', 'captchaapi'), 'captchaapi.eu'),
        ];
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
     * The widget and everything it depends on are deferred together.
     *
     * Deferring only the widget would invert the order it relies on: a plain
     * script runs while the page is still parsing, so the marker and the badge
     * would postpone their work to DOMContentLoaded, while the deferred widget
     * runs before that event and could scan a page that has not been marked yet.
     * Deferred scripts execute in document order, so deferring all three
     * restores the sequence the dependency chain describes.
     *
     * @param string $tag
     * @param string $handle
     */
    public function defer_widget($tag, $handle): string
    {
        $deferred = ['captchaapi', 'captchaapi-forms', 'captchaapi-badge'];

        if (in_array($handle, $deferred, true) && strpos($tag, ' defer') === false) {
            $tag = str_replace(' src=', ' defer src=', $tag);
        }

        return $tag;
    }

    private function locale(): string
    {
        return strtolower(substr(get_locale(), 0, 2));
    }
}
