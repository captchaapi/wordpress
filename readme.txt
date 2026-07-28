=== GDPR Cookieless CAPTCHA for WooCommerce & Forms - captchaapi.eu ===
Contributors: rajtik
Tags: captcha, recaptcha, spam, contact form, gdpr
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookieless, EU-hosted reCAPTCHA alternative for WooCommerce, WPForms, Fluent Forms & CF7. GDPR-clean, no cookie banner.

== Description ==

Protects WooCommerce (login, registration, lost password, checkout), Contact Form 7, WPForms, Fluent Forms, Formidable Forms, Forminator, Gravity Forms and Elementor Forms - cookieless, EU-hosted, no cookie banner required.

A privacy-first alternative to reCAPTCHA: captchaapi.eu stops form spam without making your visitors click traffic lights. A free tier with commercial use allowed gets you started. The work happens in the background: the visitor's browser solves a small proof-of-work puzzle while they fill in the form, and a token rides along with the submission. There is nothing to solve and nothing to see.

When a form is submitted, your server confirms that token with captchaapi.eu over a single request, secured by your secret key. It is the same model every hosted CAPTCHA uses, and it keeps the secret on your server, never in the browser.

= Privacy by design =

* No cookies, and nothing to add to a cookie banner.
* No tracking and no visitor profile. The IP address is used only for rate limiting and abuse detection, then dropped; it is never written to a database.
* Hosted only in the EU, in Nuremberg, Germany. No data leaves the EU.
* No images and no puzzles to solve. The check runs in the background, so it works the same for every visitor, including people who find image challenges difficult or browse with a screen reader.
* A free tier, with commercial use allowed.

= Forms and plugins it protects =

WordPress core:

* Login (wp-login.php)
* Registration
* Lost password
* Comments

WooCommerce:

* Login
* Registration
* Lost password
* Checkout

Form plugins:

* Contact Form 7
* WPForms
* Fluent Forms
* Formidable Forms
* Forminator
* Gravity Forms
* Elementor Forms

Each form can be turned on or off from the settings screen. The WooCommerce and form-plugin options appear only when that plugin is active.

= How it works =

1. The widget loads on the pages with a protected form and solves a proof-of-work puzzle in a Web Worker.
2. On submit, it attaches the resulting token to the form.
3. The plugin confirms the token with captchaapi.eu using your secret key and rejects the submission if the service does not accept it.

Each token verifies exactly once - the service enforces single use - so the plugin keeps no local replay table and nothing to clean up on a schedule.

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

= What do visitors see on a protected form? =

One line of small text under each protected form, saying whether the protection is running. No box, no background, no checkbox to click. Because there is no puzzle to solve, without it there would be no sign at all that the form is protected - not for your visitors and not for you.

You can add our logo and a link to captchaapi.eu to that line, or turn the whole thing off, under Settings -> captchaapi.eu. The version with our logo is a credit on your public pages, so it is never on unless you pick it.

= Does form submission slow down? =

Verification is a single server-to-server request on submit, with a short timeout. The browser does its proof-of-work in the background before the submit, usually in well under a second.

= What happens if captchaapi.eu is unreachable? =

By default the plugin fails closed: a missing or unverified token is rejected rather than waved through. If you would rather keep forms working during an outage, turn on the optional failsafe mode: when the verify request cannot reach captchaapi.eu, it lets submissions through and automatically resumes strict protection once the service is back.

Logging in and resetting a password are the exception. Those two forms are never blocked by anything on our side, so an outage or an account problem can never shut you out of your own site.

= What happens when my free tier runs out? =

Protected forms are rejected until you top up, and a notice in your dashboard tells you so. Protection never drops silently to "no captcha" - you would think you were protected while you were not. Logging in and resetting a password keep working, so you can always reach your settings and your captchaapi.eu account.

= Does it work with Contact Form 7? =

Yes. Enable Contact Form 7 in the settings. The plugin acquires a token before Contact Form 7 sends the form and verifies it on the server.

= Which form plugins are supported? =

WooCommerce, WPForms, Fluent Forms, Formidable Forms, Forminator, Gravity Forms, and Elementor Forms, in addition to Contact Form 7. Enable each from the settings screen; the option appears only when that plugin is active. The plugin attaches a token before the form is sent and verifies it on the server.

= Do you set cookies or track visitors? =

No cookies, no profiling, and no third-party requests beyond the widget talking to the API. The visitor's IP address is used only transiently for rate limiting and abuse/bot detection; it is not stored in a database and is not used to build a visitor profile.

= Where is the data processed? =

On servers in the EU.

= Which login forms are covered? =

The standard WordPress login form at wp-login.php and the WooCommerce account login form. Other custom login forms are not covered in this version.

= Does it protect XML-RPC? =

No. The check is a browser-side proof of work, so it only runs on real form submissions in a browser. XML-RPC and the REST API are not browsers, so they are left untouched and a captcha cannot gate them. If you do not use XML-RPC, disabling it separately closes that brute-force surface.

= Does it work on multisite? =

This version targets single-site installs. Network signup through wp-signup.php is not covered yet.

== External services ==

This plugin connects to captchaapi.eu, a third-party CAPTCHA service, to protect your forms from spam. It is required for the plugin to function.

On any public page that contains a protected form, the plugin loads the service's widget script (captcha.js) from your configured captchaapi.eu endpoint. The visitor's browser then communicates with the captchaapi.eu API to perform a proof-of-work challenge and obtain a token that is attached to the form on submit. This happens for every visitor who loads a protected form.

To issue and validate a token the service receives your public site key, the proof-of-work result, and - as with any HTTP request - the visitor's IP address. The IP address is used for rate limiting and abuse/bot detection (including a coarse, IP-derived country) and is processed transiently: a hashed form and aggregate counters are held briefly in a cache. No raw IP address and no per-visitor record are written to a database. The service sets no cookies. Data is processed on servers in the EU (Nuremberg, Germany).

When a protected form is submitted, your server sends the token to the captchaapi.eu /verify endpoint, authenticated with your secret key, and trusts the service's accept-or-reject answer. The secret key stays on your server and is never sent to the browser.

Your server also asks the captchaapi.eu /captcha/challenge endpoint whether it would still issue challenges for your site key. This happens in two situations: when you press "Test connection" on the settings screen, and when a protected form arrives with no token at all - which can mean either a stripped submission or a widget that never received a challenge, and the plugin has to know which before it rejects a real visitor. The request sends your public site key and your site's address; the answer is cached for a few minutes, so a burst of submissions does not become a burst of requests. No visitor data is sent.

When you open the plugin's settings screen, it asks the captchaapi.eu /api/v1/stats endpoint how much of your account's monthly allowance has been used, so the Activity panel can show it. The request is authenticated with your secret key and carries nothing about your visitors. It runs only for administrators, only on that screen, and the answer is cached for twelve hours.

* Service provider: captchaapi.eu
* Terms of Service: https://captchaapi.eu/legal/terms
* Privacy Policy: https://captchaapi.eu/legal/privacy

== Changelog ==

= 2.1.0 =
* New: an Activity panel on the settings screen. It answers the question an invisible captcha cannot answer on its own - is this thing working? Two figures come from your own site (submissions verified, submissions turned away) and one from captchaapi.eu (how much of your account's monthly allowance is gone). They are kept apart on purpose: a challenge that is issued and never submitted counts for the service and not for your site, so adding them together would produce a number that means nothing.
* When the service says it cannot issue challenges, the panel leads with that rather than with the figures. Account totals lag live traffic by up to fifteen minutes, and "4,800 of 5,000" reads as headroom to a site whose forms are already being turned away.
* Every protected form now carries a single line reporting whether the protection is running - WordPress core, WooCommerce including the checkout, and all seven supported form plugins. There is no puzzle and no checkbox to see, so until now neither you nor your visitors had any sign the plugin was working. It draws no box and no background, and each form gets its own: a WooCommerce account page shows one under the login form and one under the registration form.
* Optional: the same line can carry our logo and a link to captchaapi.eu. That is a credit on your public pages, so it is off unless you choose it under Settings -> captchaapi.eu.
* Both can be turned off entirely.
* The captchaapi.eu widget script is no longer stamped with the plugin's version number. It is the service's file on the service's release schedule, and tying it to ours meant a browser kept the build that shipped with your plugin version until the next plugin update.

= 2.0.3 =
* Fixed: logging in and resetting a password are never blocked by the state of your captchaapi.eu account. Previously a used-up free tier, a suspended account, or an unreachable service could reject the wp-login.php form and shut you out of your own site.
* Rejections now say why. Administrators see the reason next to the error, and a notice in the dashboard explains what to do when protected forms are being turned away.
* Failsafe mode is now clearly scoped to outages on our side. When the account cannot issue challenges at all - free tier used up, account suspended, project inactive - protected forms are rejected regardless of the setting, so protection never drops silently.
* Verification and connection checks time out after 3 seconds instead of 5, so a slow service does not hold up a submission.

= 2.0.2 =
* Stopped marking product names and the captchaapi.eu brand as translatable strings, so the translation list only contains real interface text.

= 2.0.1 =
* Clearer directory listing: grouped the protected forms by WordPress core, WooCommerce, and form plugins, added Gravity Forms and Elementor Forms to the list, refreshed the tags, and added a "Privacy by design" summary. No code changes.

= 2.0.0 =
* Verification is now a server-to-server call. The plugin confirms each token with the captchaapi.eu /verify endpoint using your secret key, instead of checking a signed token locally. The form field is now `captchaapi_response`.
* The service enforces single use, so the local replay table and its hourly purge cron are gone - both are removed automatically when you upgrade.
* Failsafe mode now keys off the verify request: when captchaapi.eu cannot be reached on submit, failsafe lets the form through; a definite rejection always blocks.

= 1.1.2 =
* Refreshed the plugin icon and directory banner with the new captchaapi.eu branding.

= 1.1.1 =
* Clearer directory listing: updated title, tags, and description to highlight the cookieless, EU-hosted protection and the supported form plugins.
* Documented failsafe mode in the FAQ: forms can stay usable during a captchaapi.eu outage, then strict protection resumes automatically.

= 1.1.0 =
* Added integrations for WooCommerce (login, registration, lost password, and checkout), WPForms, Fluent Forms, Formidable Forms, and Forminator.
* Added a "Test API response" button that checks the service is reachable and the keys are in the right fields.
* Added failsafe mode: an optional fallback that keeps forms usable while captchaapi.eu is unreachable, then resumes strict protection automatically.
* Settings screen: grouped into sections (account keys, protected forms, behavior, advanced), a "Get your free keys" call to action when no keys are set, key format hints in the fields, and a clearer warning that the secret key must stay on the server.
* Added translations: Czech, German, French, Spanish, Italian, Polish, Dutch, Portuguese, and Romanian.

= 1.0.1 =
* Compatibility and Plugin Check fixes for the WordPress.org directory: updated "Tested up to", aligned the plugin name with the readme, versioned the enqueued widget script, prefixed an uninstall global, and dropped the redundant load_plugin_textdomain() call.
* Documented the captchaapi.eu external service in the readme, including the data sent and links to the Terms of Service and Privacy Policy.

= 1.0.0 =
* First release. Protects login, registration, lost password, comments, and Contact Form 7.
