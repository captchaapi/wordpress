# readme translation workflow (WordPress.org)

The plugin's WordPress.org listing is translated on translate.wordpress.org, not
in this repository. The strings there are cut from `readme.txt` by their parser,
so a translation is only importable if it carries **their** msgids, byte for
byte. That is why the source of truth here is an export from GlotPress and not
anything generated locally.

## 1. Pull the current strings

    curl -sL -o tools/translation/source/captchaapi-readme-de.po \
      "https://translate.wordpress.org/projects/wp-plugins/captchaapi/stable-readme/de/default/export-translations/?format=po"

Swap `de` for another locale in both the URL and the filename. Re-pull after
every readme change - new strings appear and edited ones become new msgids.

## 2. Build the document

    python3 tools/translation/build_translator_doc.py de

Writes to `tools/translation/out/`:

- `captchaapi-readme-de-TRANSLATION.docx` - **this is what you send.** The brief
  and all strings in one Word file, each with a `DE:` line to fill in
- `captchaapi-readme-de-TRANSLATION.md` - the same document as plain text
- `captchaapi-readme-de.csv` - strings only, for a spreadsheet
- `captchaapi-readme-de.po` - strings only, for Poedit / OmegaT

The brief comes from `TRANSLATOR-BRIEF.md` and is copied into the document, so
edit it there and rebuild. It holds the register (informal "du"), the
do-not-translate list and the glossary bound to the plugin's German interface.

The `.docx` is built with `textutil`, which is macOS only. Elsewhere the script
skips it and the Markdown document stands on its own.

## 3. Merge what comes back

    python3 tools/translation/merge_translations.py <the-file-they-sent> de

Takes the `.docx`, the `.md` or the `.csv`; a returned `.po` needs no merge. It
matches by the `[id]` markers, writes
`tools/translation/out/captchaapi-readme-de-translated.po`, and reports what
would otherwise slip through an import: HTML entities that changed, URLs that
went missing, a short description over 150 characters, and questions that lost
their question mark.

## 4. Import

Go to the locale's readme project on translate.wordpress.org, *Import
Translations*, upload the `-translated.po`. Suggestions land as waiting strings
unless you are a PTE for that locale; a German PTE then approves them.

## Notes

- `tools/` is in `.distignore`, so none of this ships in the plugin zip.
- The `.md` and `.csv` worksheets are generated - the file the translator
  returns is the artifact worth keeping.
- Keep the glossary in `TRANSLATOR-BRIEF.md` in step with
  `languages/captchaapi-de_DE.po`. The interface and the listing being
  translated by different people at different times is how a plugin ends up
  calling the same thing two names.
