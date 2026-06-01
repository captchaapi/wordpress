# captchaapi.eu for WordPress

Proof-of-work CAPTCHA for WordPress. No puzzles for your visitors, no cookies, no tracking. The browser solves a small proof-of-work in the background and attaches a signed token to the form; your server verifies that token locally with your secret key.

Protects the login, registration, lost-password, and comment forms, plus Contact Form 7.

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer
- A captchaapi.eu project ([sign up](https://captchaapi.eu) for a site key and a secret key)

## Installation

1. Copy this folder to `wp-content/plugins/captchaapi`, or build a zip and install it from the Plugins screen.
2. Activate **captchaapi.eu**.
3. Go to **Settings → captchaapi.eu**, enter your site key and secret key, pick the forms to protect, and save.

## Configuration

Keys can come from the settings screen or from `wp-config.php`. A constant always overrides the stored value, which keeps the secret key out of the database:

```php
define( 'CAPTCHAAPI_SITE_KEY', 'your_site_key' );
define( 'CAPTCHAAPI_SECRET_KEYS', 'your_secret_key' );
```

During a key rotation, list both keys so submissions signed by either one still verify:

```php
define( 'CAPTCHAAPI_SECRET_KEYS', 'current_key,new_key' );
```

If you self-host or proxy the API, set the base URL too:

```php
define( 'CAPTCHAAPI_BASE_URL', 'https://captcha.example.com' );
```

## How verification works

There is no server-to-server call on submit. The attestation is `base64url(payload).base64url(hmac_sha256(payload, secret))`. The plugin:

1. Recomputes the HMAC with each configured secret key and compares it in constant time.
2. Checks that the payload has not expired and was minted for this site key.
3. Claims the token's `jti` once, atomically, so it cannot be replayed.

The single-use claim uses the object cache when a persistent one is available, and a small database table otherwise. Expired rows in that table are cleared by an hourly task.

## Forms

| Form | Submission | How it is wired |
| --- | --- | --- |
| Login, registration, lost password, comments | Native POST | Tagged with `data-captcha`; verified in the matching WordPress hook |
| Contact Form 7 | AJAX | A capture-phase gate fetches an attestation via `window.captchaapi.solve()`, then lets CF7 send the form; verified through the `wpcf7_spam` filter |

Contact Form 7 can't use the widget's submit interception, because it attaches its own submit handler that fires unconditionally. The gate in `assets/js/captchaapi-cf7.js` sidesteps that by acquiring the attestation first and resubmitting through CF7's own path.

## Limitations

- Single-site only. Multisite network signup (`wp-signup.php`) is not covered yet.
- "Login" protects the `wp-login.php` form. WooCommerce and other custom login forms are out of scope in this version.
- A browser-side proof of work can't gate XML-RPC or the REST API, so those paths are left untouched. Disable XML-RPC separately if it is a concern.

## Layout

```
captchaapi.php                     Plugin bootstrap
uninstall.php                      Removes the option, table, and scheduled task
includes/
  class-captchaapi-options.php     Settings and key resolution
  class-captchaapi-verifier.php    Local HMAC verification
  class-captchaapi-replay-store.php Single-use token storage
  class-captchaapi-gate.php        Per-request verify + replay check
  class-captchaapi-assets.php      Widget and marker enqueue
  class-captchaapi-settings.php    Admin settings screen
  class-captchaapi-plugin.php      Wiring and activation hooks
  integrations/
    class-captchaapi-core-forms.php
    class-captchaapi-contact-form-7.php
assets/js/captchaapi-forms.js      Tags core forms with data-captcha
assets/js/captchaapi-cf7.js        Contact Form 7 gate
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
