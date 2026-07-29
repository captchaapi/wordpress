# Review ledger

Rozhodnutí o nálezech z `/self-code-review`. Účel: zamítnutý nález se nemá vracet v dalším běhu a agenti si nemají protiřečit napříč koly.

Verzovaný schválně. Kdyby ležel mezi lokální konfigurací nástrojů, nepushoval by se - a rozhodnutí, které si pamatuje jen jeden stroj, udělá příští běh znovu. `tools/` je v `.distignore`, takže se do zipu pro WordPress.org nedostane.

Prořezávání: záznam o souboru, třídě nebo metodě, které už neexistují, se maže - důvod zanikl spolu s kódem. Obecné záznamy zůstávají.

## Obecně - modely hrozby předpokládající prolomené TLS nebo zlomyslné captchaapi.eu
- Datum: 2026-07-29
- Verdikt: ZAMÍTNUTO
- Důvod: Vlastní službě se věří, jinak nemá plugin co dělat. Přijímají se jen levné meze proti chybující službě (strop na délku klíče a těla odpovědi), ne obrana proti ní.

## class-captchaapi-settings.php:render() - metoda přes 80 řádků (207)
- Datum: 2026-07-29
- Verdikt: ZAMÍTNUTO
- Důvod: Souvislá šablona nastavovací obrazovky, ne logika. Existuje dávno před tímhle diffem. Rozdělení by vyrobilo metody bez vlastní odpovědnosti, jen aby se snížil počet řádků.

## class-captchaapi-assets.php:enqueue_frontend() - metoda přes 80 řádků (94)
- Datum: 2026-07-29
- Verdikt: ZAMÍTNUTO
- Důvod: Totéž. Předchází tomuhle diffu, žádný běh se jí nedotkl.

## class-captchaapi-connect.php:render_result/render_button - render metody patří do Captchaapi_Settings
- Datum: 2026-07-29
- Verdikt: ZAMÍTNUTO
- Důvod: Opačné doporučení padlo v témže běhu o kolo dřív. `Captchaapi_Settings` má 677 řádků, přesun by god class zhoršil; connect UI patří odpovědností ke connect flow.

## class-captchaapi-connect.php:render_sign_in_hint - odkaz na přihlášení má používat site_url(), ne connect_base_url()
- Datum: 2026-07-29
- Verdikt: ZAMÍTNUTO
- Důvod: Záměr. `CAPTCHAAPI_CONNECT_BASE_URL` je vývojářská konstanta a při testování proti lokální službě má na ni mířit i přihlášení.

## class-captchaapi-connect.php:clean_key - vynutit prefix pk_/sk_
- Datum: 2026-07-29
- Verdikt: ZAMÍTNUTO
- Důvod: Postavilo by to představu pluginu o formátu klíče nad server. Změna formátu na serveru by rozbila connect na instalacích, které nejde aktualizovat. Strop na délku přijat, prefix ne.

## class-captchaapi-connect.php:maybe_handle_return - chybí WP nonce na návratu
- Datum: 2026-07-29
- Verdikt: ZAMÍTNUTO
- Důvod: Návrat přichází z captchaapi.eu, referer je cizí a nonce tam nelze uplatnit. Roli CSRF tokenu plní `state` - 128 bitů z `random_bytes`, vázaný na user ID, jednorázový, porovnávaný `hash_equals()`.

## class-captchaapi-connect.php:render_result - podvržená hláška z query stringu
- Datum: 2026-07-29
- Verdikt: PŘIJATO částečně
- Důvod: Plná oprava (jednorázový transient) je nepoměrná - WordPress sám nese `settings-updated` stejně. Přijata levná varianta: `RESULT_OK` a `RESULT_CONFIGURED` se ověřují proti skutečnému stavu, zbytek zůstává čtený z URL.
