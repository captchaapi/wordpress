# WordPress.org screenshots

Regenerates `.wordpress-org/screenshot-*.png` from a live WordPress install, so
the pictures in the plugin directory keep matching the code that produced them.
A `.wordpress-org/**` change on `main` syncs them to the directory on its own -
see `.github/workflows/assets.yml`. The captions live in `readme.txt` under
`== Screenshots ==`, and their order has to match the file numbers.

Nothing here ships: `tools/` is excluded from the plugin zip.

## What you need

* A WordPress install with this plugin active, reachable over HTTP(S), with
  saved keys pointing at a captchaapi.eu API that answers. Screenshots 3 and 4
  make real requests to it - a green "Connected" that was faked is worse than no
  screenshot at all.
* The form plugins you want to see in screenshot 2 (Contact Form 7,
  WooCommerce, WPForms, Fluent Forms, Formidable, Forminator, Gravity Forms,
  Elementor Pro). Each one only appears once it is active.
* `wp-cli` on `PATH`, or `wp-cli.phar` in the install's root.
* Node and Chrome.

## Running it

```sh
cd tools/screenshots
npm install
WP_PATH=/path/to/wp npm run shoot        # all seven
WP_PATH=/path/to/wp npm run shoot -- 2 5 # only those
```

`WP_PATH` defaults to `../../../wp-test`, i.e. a `wp-test` install sitting next
to this plugin checkout. `WP_USER` picks the administrator whose wp-admin gets
opened (default `1`); no password is involved - the run mints an auth cookie
through wp-cli.

## What it does to the site

The interesting screens are ones a fresh test install never reaches, so the run
stages them and puts everything back afterwards. It saves `captchaapi_options`,
`captchaapi_stats`, `captchaapi_last_service_state` and `WPLANG` first, then:

* switches the admin to English - the directory listing is read in English;
* fills in a month of local counters and an account at 2,640 of 5,000 for the
  Activity panel;
* for screenshot 6, marks the account as over its free tier limit.

If a run dies partway through, the site is left staged. `npm run restore`
finishes the job.

The site key is real while "Test connection" runs and is replaced with a
placeholder in the DOM before the shutter, so no live key reaches the directory.
