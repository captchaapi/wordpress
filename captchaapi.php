<?php
/**
 * Plugin Name:       captchaapi.eu Proof-of-Work CAPTCHA
 * Plugin URI:        https://captchaapi.eu/docs
 * Description:       Privacy-first proof-of-work CAPTCHA. Protects the login, registration, lost-password, and comment forms, plus Contact Form 7. EU-hosted, no cookies, no tracking.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            captchaapi.eu
 * Author URI:        https://captchaapi.eu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       captchaapi
 * Domain Path:       /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('CAPTCHAAPI_VERSION', '1.0.1');
define('CAPTCHAAPI_PLUGIN_FILE', __FILE__);
define('CAPTCHAAPI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CAPTCHAAPI_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-options.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-verifier.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-replay-store.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-gate.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-assets.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-settings.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/integrations/class-captchaapi-core-forms.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/integrations/class-captchaapi-contact-form-7.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-plugin.php';

register_activation_hook(__FILE__, ['Captchaapi_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Captchaapi_Plugin', 'deactivate']);

add_action('plugins_loaded', function () {
    (new Captchaapi_Plugin(new Captchaapi_Options()))->boot();
});
