# BRIEF - captchaapi.eu plugin readme, English → German (de_DE)

**You have two files.** This one is the brief: what the text is, how it should
sound, and the rules that decide whether the translation is accepted. Nothing
in it is translated.

The second file, **`{strings_file}`**, holds the {count} strings to translate.
Read this brief first, then work in that one.

## What this is

The text you are translating is the WordPress.org directory listing for the
plugin **GDPR Cookieless CAPTCHA for WooCommerce & Forms - captchaapi.eu**.
It is what a site owner reads before deciding to install the plugin: the
description, the installation steps, the FAQ, the screenshot captions and the
changelog.

It is marketing copy and technical documentation at the same time. It should
read like it was written in German, not like an English page seen through
glass.

**Machine translation is not acceptable here.** The German translation is
reviewed by a WordPress.org Project Translation Editor (PTE) before it goes
live, and machine output is rejected on sight under the WordPress polyglots
rules. Post-edited machine output usually is too - the giveaways are literal
word order, English compounds left as two words, and "Sie"/"du" drifting
within one paragraph.

## Where the text ends up

At `https://translate.wordpress.org/projects/wp-plugins/captchaapi/stable-readme/de/default/`
and, once approved, on the plugin's page in the German plugin directory.

## How to work

The strings file holds all {count} strings, in the order a reader meets them on
the page. Under each English string is a `DE:` line - type the German there and
leave the rest of the line structure alone.

Every string carries an `[id]` such as `[de6aa70e]`. That id is how your text
finds its way back to the right record in the WordPress.org system, so do not
delete or edit the ids, and do not reorder, merge or split strings.

If you would rather work in a CAT tool, ask - the same strings are also
available as a `.csv` for spreadsheets and as a `.po` for Poedit or OmegaT,
carrying the same ids. Return whichever one you filled in.

## Voice and register

- **Use informal "du"**, not "Sie". The German WordPress locale `de_DE` is the
  informal one; the formal variant is a separate locale we are not filling in
  here. The plugin's own interface is already translated with "du" / "dein"
  ("Prüfe, ob du den site key korrekt kopiert hast."), and the readme must
  match it.
- Address the site owner, not the site's visitors. "deine Formulare",
  "deine Besucher".
- Confident and plain. Short sentences. The English avoids hype and so should
  the German - no "revolutionär", no "einfach genial", no exclamation marks.
- FAQ headings are questions and stay questions.
- Prefer real German compounds over spaced English ones: "Cookie-Banner", not
  "Cookie Banner".

## Terms that stay in English

Product and file names, exactly as written:

WooCommerce · Contact Form 7 · WPForms · Fluent Forms · Formidable Forms ·
Forminator · Gravity Forms · Elementor Forms · WordPress · WordPress.org ·
reCAPTCHA · captchaapi.eu · CAPTCHA · Web Worker · XML-RPC · REST API ·
`wp-login.php` · `wp-config.php` · `wp-signup.php` · `wp-content/plugins/captchaapi` ·
`captchaapi_response` · `captcha.js` · `CAPTCHAAPI_SECRET_KEYS` ·
`/verify` · `/captcha/challenge` · `/api/v1/stats`

Also leave untouched:

- **URLs** - every `https://…` must survive unchanged.
- **Code lines** such as `define( 'CAPTCHAAPI_SECRET_KEYS', 'your_secret_key' );`.
  Translate the sentence around them, never the code.
- **Version numbers** in the changelog (`= 2.1.1 =` style headings are not in
  your files at all - only the bullet text under them is).

## Things that will break the import

- **HTML entities must be carried over as they stand.** Some strings contain
  `&amp;` (an ampersand), `&gt;` (a `>`), `&#8211;` (a dash). If the English
  has `Settings -&gt; captchaapi.eu`, the German has
  `Einstellungen -&gt; captchaapi.eu` - the `&gt;` stays an entity.
- **The short description has a hard limit of 150 characters.** It is the one
  string marked *high priority* under "Short description". German runs longer
  than English, so this one usually has to be rewritten rather than
  translated. Cut a clause instead of running over.
- **Do not add a trailing period** where the English has none, and do not drop
  one where it has one - several strings are list items and headings.

## Menu paths

`Settings -> captchaapi.eu` is a path through the WordPress admin menu. Use the
official German WordPress term for the menu item: **`Einstellungen -&gt; captchaapi.eu`**.
The plugin's own name in the menu stays `captchaapi.eu`.

## Glossary

Bound by the plugin's existing German interface - please keep these consistent.

| English | German | Note |
| --- | --- | --- |
| site key | Site Key | Capitalised as a German noun. |
| secret key | Secret Key | |
| key rotation | Schlüsselwechsel | |
| token | Token | m., der Token |
| proof-of-work | Proof-of-Work | Keep the term; "Rechenaufgabe" may be added as an aside once, on first use, if it helps. |
| free tier | kostenloser Tarif | "Das Limit des kostenlosen Tarifs" is already in the interface. |
| failsafe mode | Failsafe-Modus | |
| fails closed | blockiert im Zweifel | Not literal - the meaning is "rejects rather than waves through". |
| protected form | geschütztes Formular | |
| login (form) | Anmeldung / Anmeldeformular | |
| registration | Registrierung | |
| lost password | Passwort vergessen | |
| comments | Kommentare | |
| checkout | Kasse | "WooCommerce-Kasse" is already in the interface. |
| settings screen | Einstellungsseite | |
| Test connection | Verbindung testen | Button label, already in the interface. |
| Activity panel | Aktivitäts-Panel | Name of a panel on the settings screen; keep it identical everywhere. |
| allowance (monthly) | Kontingent | "monatliches Kontingent" |
| verification | Überprüfung | |
| rate limiting | Rate Limiting | |
| abuse detection | Missbrauchserkennung | |
| endpoint | Endpoint | |
| widget | Widget | |
| cookie banner | Cookie-Banner | |
| GDPR | DSGVO | The German abbreviation, always. |
| screen reader | Screenreader | |
| single-site install | Single-Site-Installation | |
| multisite | Multisite | |

## Two notes on content

- The plugin is deliberately described as **invisible**: no images, no
  checkbox, nothing for a visitor to solve. Keep that claim intact - do not
  soften it into "kaum sichtbar".
- Privacy claims are legal statements: EU hosting, Nuremberg, no cookies, no
  visitor profile, IP used transiently. Translate them precisely, do not
  strengthen or weaken them, and do not add claims the English does not make.

## Delivery

Send back the strings file with the `DE:` lines filled in - the brief does not
come back. We run an automated check on it (entities, URLs, the 150-character
limit) and import it into translate.wordpress.org, where a German PTE reviews
it string by string.

Questions about a string are welcome before delivery - a query costs less than
a rejected review round.
