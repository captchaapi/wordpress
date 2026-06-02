=== captchaapi.eu Proof-of-Work CAPTCHA ===
Contributors: captchaapi
Tags: captcha, spam, login, comments, antispam
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Proof-of-work CAPTCHA with no puzzles, no cookies, and no tracking. Protects your login, registration, comment, and Contact Form 7 submissions.

== Description ==

captchaapi.eu stops form spam without making your visitors click traffic lights. The work happens in the background: the visitor's browser solves a small proof-of-work puzzle while they fill in the form, and a signed token rides along with the submission. There is nothing to solve and nothing to see.

Your server checks that token locally with your secret key. No request is sent back to captchaapi.eu when a form is submitted, so the check adds no network latency and keeps working even if our service is briefly unreachable.

The service runs on hardware in the EU (Nuremberg, Germany). It sets no cookies and writes no per-visitor record to a database; the visitor's IP address is used only transiently for rate limiting and abuse detection.

= What it protects =

* Login (wp-login.php)
* Registration
* Lost password
* Comments
* Contact Form 7

Each surface can be turned on or off from the settings screen. Contact Form 7 support appears only when that plugin is active.

= How it works =

1. The widget loads on the pages with a protected form and solves a proof-of-work puzzle in a Web Worker.
2. On submit, it attaches a short-lived, signed attestation to the form.
3. The plugin verifies the attestation with your secret key (an HMAC check) and rejects the submission if it is missing, forged, expired, or reused.

Reuse is blocked with a single-use record per token. If your site has a persistent object cache (Redis or Memcached), that record lives there. Otherwise the plugin keeps a small table and clears expired rows on a schedule.

= You need an account =

This plugin connects to the captchaapi.eu service. Create a project at https://captchaapi.eu to get a site key and a secret key. A free tier is available.

== Installation ==

1. Upload the plugin to `wp-content/plugins/captchaapi`, or install it from the Plugins screen.
2. Activate it.
3. Open Settings -> captchaapi.eu.
4. Enter your site key and secret key from your project dashboard.
5. Choose which forms to protect and save.

For a stricter setup, keep the secret key out of the database by defining it in `wp-config.php`:

`define( 'CAPTCHAAPI_SECRET_KEYS', 'your_secret_key' );`

During a key rotation, list the current and the new key together, separated by a comma:

`define( 'CAPTCHAAPI_SECRET_KEYS', 'current_key,new_key' );`

== Frequently Asked Questions ==

= Do my visitors have to solve anything? =

No. There is no image challenge and no checkbox. The proof-of-work runs in the browser while the form is being filled in.

= Does form submission slow down? =

The verification is a local HMAC check, so it adds no network round trip on submit. The browser does its proof-of-work in the background before the submit, usually in well under a second.

= What happens if captchaapi.eu is unreachable? =

The widget will not produce an attestation, so a protected form will not submit. The plugin fails closed by design: a submission without a valid attestation is rejected rather than waved through.

= Does it work with Contact Form 7? =

Yes. Enable Contact Form 7 in the settings. The plugin acquires an attestation before Contact Form 7 sends the form and verifies it on the server.

= Do you set cookies or track visitors? =

No cookies, no profiling, and no third-party requests beyond the widget talking to the API. The visitor's IP address is used only transiently for rate limiting and abuse/bot detection; it is not stored in a database and is not used to build a visitor profile.

= Where is the data processed? =

On servers in the EU.

= Which login forms are covered? =

The standard WordPress login form at wp-login.php. WooCommerce and other custom login forms are not covered in this version.

= Does it protect XML-RPC? =

No. The check is a browser-side proof of work, so it only runs on real form submissions in a browser. XML-RPC and the REST API are not browsers, so they are left untouched and a captcha cannot gate them. If you do not use XML-RPC, disabling it separately closes that brute-force surface.

= Does it work on multisite? =

This version targets single-site installs. Network signup through wp-signup.php is not covered yet.

== External services ==

This plugin connects to captchaapi.eu, a third-party CAPTCHA service, to protect your forms from spam. It is required for the plugin to function.

On any public page that contains a protected form, the plugin loads the service's widget script (captcha.js) from your configured captchaapi.eu endpoint. The visitor's browser then communicates with the captchaapi.eu API to perform a proof-of-work challenge and obtain a signed attestation that is attached to the form on submit. This happens for every visitor who loads a protected form.

To issue and validate an attestation the service receives your public site key, the proof-of-work result, and - as with any HTTP request - the visitor's IP address. The IP address is used for rate limiting and abuse/bot detection (including a coarse, IP-derived country) and is processed transiently: a hashed form and aggregate counters are held briefly in a cache. No raw IP address and no per-visitor record are written to a database. The service sets no cookies. Data is processed on servers in the EU (Nuremberg, Germany).

Verification of the attestation on submit is performed locally on your server with your secret key; no request is sent back to captchaapi.eu at that point.

* Service provider: captchaapi.eu
* Terms of Service: https://captchaapi.eu/legal/terms
* Privacy Policy: https://captchaapi.eu/legal/privacy

== Changelog ==

= 1.0.1 =
* Compatibility and Plugin Check fixes for the WordPress.org directory: updated "Tested up to", aligned the plugin name with the readme, versioned the enqueued widget script, prefixed an uninstall global, and dropped the redundant load_plugin_textdomain() call.
* Documented the captchaapi.eu external service in the readme, including the data sent and links to the Terms of Service and Privacy Policy.

= 1.0.0 =
* First release. Protects login, registration, lost password, comments, and Contact Form 7.
