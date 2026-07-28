<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Tells the site owner when protected forms are being turned away, and why.
 *
 * A hard cap that nobody can explain is indistinguishable from a broken plugin,
 * so the state the gate recorded is surfaced on every admin screen rather than
 * only on the settings page. Nothing here talks to the network: it reads the
 * verdict the gate stored on the last real submission.
 */
class Captchaapi_Notices
{
    /**
     * An outage older than this is treated as over. A used-up quota is not - it
     * stays until a submission verifies again.
     */
    const OUTAGE_TTL = HOUR_IN_SECONDS;

    private Captchaapi_Options $options;

    private Captchaapi_Service $service;

    public function __construct(Captchaapi_Options $options, Captchaapi_Service $service)
    {
        $this->options = $options;
        $this->service = $service;
    }

    public function boot(): void
    {
        add_action('admin_notices', [$this, 'render']);
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $problem = $this->service->last_problem();

        if ($problem === null) {
            return;
        }

        if ($problem['state'] === Captchaapi_Service::NOT_ENFORCEABLE) {
            $this->render_blocked($problem['code']);

            return;
        }

        if ($problem['time'] > 0 && (time() - $problem['time']) < self::OUTAGE_TTL) {
            $this->render_outage();
        }
    }

    /**
     * The account or the project is in the way. Protected forms are being
     * rejected until the owner acts, so this notice does not go away on its own.
     */
    private function render_blocked(string $code): void
    {
        printf(
            '<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p><p>%3$s <a href="%4$s" target="_blank" rel="noopener noreferrer">%5$s</a></p></div>',
            esc_html__('captchaapi.eu is not protecting your forms.', 'captchaapi'),
            esc_html(Captchaapi_Gate::reason_for($code)),
            esc_html__('Protected forms are being rejected until this is resolved. Logging in and resetting a password keep working.', 'captchaapi'),
            esc_url($this->options->site_url()),
            esc_html__('Open your captchaapi.eu dashboard', 'captchaapi')
        );
    }

    /**
     * Our side is down. Nothing for the owner to buy or configure, so this one
     * only explains what they are seeing.
     */
    private function render_outage(): void
    {
        $message = $this->options->failsafe()
            ? __('captchaapi.eu was unreachable recently. Failsafe mode is on, so submissions are going through unverified until the service answers again.', 'captchaapi')
            : __('captchaapi.eu was unreachable recently, so protected forms are being rejected. Turn on failsafe mode in the settings to let submissions through during an outage.', 'captchaapi');

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            esc_html($message)
        );
    }
}
