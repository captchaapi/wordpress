<?php
/**
 * Plugin Name:       GDPR Cookieless CAPTCHA for WooCommerce & Forms - captchaapi.eu
 * Plugin URI:        https://captchaapi.eu/docs
 * Description:       Cookieless, GDPR-friendly CAPTCHA hosted in the EU - a privacy-first reCAPTCHA alternative. Protects login, registration, lost-password, comments, WooCommerce, and the popular form plugins.
 * Version:           1.1.1
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

define('CAPTCHAAPI_VERSION', '1.1.1');
define('CAPTCHAAPI_PLUGIN_FILE', __FILE__);
define('CAPTCHAAPI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CAPTCHAAPI_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-options.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-verifier.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-replay-store.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-service.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-gate.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-assets.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-settings.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/integrations/class-captchaapi-core-forms.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/integrations/class-captchaapi-contact-form-7.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/integrations/class-captchaapi-woocommerce.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/integrations/class-captchaapi-wpforms.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/integrations/class-captchaapi-fluent-forms.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/integrations/class-captchaapi-formidable.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/integrations/class-captchaapi-forminator.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/integrations/class-captchaapi-gravity-forms.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/integrations/class-captchaapi-elementor-forms.php';
require_once CAPTCHAAPI_PLUGIN_DIR . 'includes/class-captchaapi-plugin.php';

register_activation_hook(__FILE__, ['Captchaapi_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['Captchaapi_Plugin', 'deactivate']);

add_action('plugins_loaded', function () {
    (new Captchaapi_Plugin(new Captchaapi_Options()))->boot();
});
