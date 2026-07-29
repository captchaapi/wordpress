"""Turn the GlotPress readme export into the files a human translator works in.

Two documents go to the translator, written to tools/translation/out:

  * captchaapi-readme-<locale>-BRIEF.md    - the rules, the glossary, the terms
                                             that stay in English. Not translated.
  * captchaapi-readme-<locale>-STRINGS.md  - the strings, each with a DE: line.
                                             This is the file that comes back.

Two side formats hold the same strings for translators who prefer their own
tools: captchaapi-readme-<locale>.csv and .po.

The changelog is left out unless --with-changelog is passed: it is a third of
the word count, and untranslated it simply stays English on the listing.

Whichever file comes back, merge_translations.py folds it into a PO that
translate.wordpress.org accepts.

Usage: python3 tools/translation/build_translator_doc.py [locale] [--with-changelog]
"""

import csv
import hashlib
import html
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import po_tools

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
README = os.path.join(ROOT, 'readme.txt')

# The order a reader meets the sections in, not the order GlotPress exports them.
SECTION_ORDER = [
    'plugin-name', 'short-description', 'description',
    'installation', 'faq', 'screenshots', 'changelog', 'other',
]

SECTION_LABEL = {
    'plugin-name': 'Plugin name (shown in the directory listing)',
    'short-description': 'Short description (max 150 characters)',
    'description': 'Description and External services',
    'installation': 'Installation',
    'faq': 'Frequently Asked Questions',
    'screenshots': 'Screenshot captions',
    'changelog': 'Changelog',
    'other': 'Other',
}


def string_id(msgid):
    return hashlib.sha1(msgid.encode('utf-8')).hexdigest()[:8]


def readme_position(msgid, readme_text):
    """Where this string sits in readme.txt, so the doc reads top to bottom."""
    needle = html.unescape(msgid).strip()

    # A short list item or heading also occurs inside earlier prose, so look for
    # the line that carries its markup before falling back to a plain search.
    anchored = ['\n* %s' % needle, '\n= %s =' % needle, '\n== %s ==' % needle]
    anchored += ['\n%d. %s' % (n, needle) for n in range(1, 10)]
    for candidate in anchored:
        position = readme_text.find(candidate)
        if position != -1:
            return position

    for candidate in (needle, needle[:60], needle[:30]):
        if len(candidate) < 10:
            break
        position = readme_text.find(candidate)
        if position != -1:
            return position
    return 10 ** 9


def sort_key(entry, readme_text):
    section = entry.section
    rank = SECTION_ORDER.index(section) if section in SECTION_ORDER else len(SECTION_ORDER)
    return (rank, readme_position(entry.msgid, readme_text))


def main():
    arguments = [a for a in sys.argv[1:] if not a.startswith('--')]
    with_changelog = '--with-changelog' in sys.argv
    locale = arguments[0] if arguments else 'de'
    source = os.path.join(HERE, 'source', 'captchaapi-readme-%s.po' % locale)
    out_dir = os.path.join(HERE, 'out')
    os.makedirs(out_dir, exist_ok=True)

    entries = po_tools.parse(source)
    header = [e for e in entries if e.is_header]
    strings = [e for e in entries if not e.is_header]

    # The changelog is a third of the word count and describes releases nobody
    # reads in a directory listing; untranslated, it simply stays English.
    dropped = 0
    if not with_changelog:
        before = len(strings)
        strings = [e for e in strings if e.section != 'changelog']
        dropped = before - len(strings)

    with open(README, encoding='utf-8') as handle:
        readme_text = handle.read()
    strings.sort(key=lambda e: sort_key(e, readme_text))

    base = os.path.join(out_dir, 'captchaapi-readme-%s' % locale)
    po_tools.write(base + '.po', header + strings)

    with open(base + '.csv', 'w', encoding='utf-8-sig', newline='') as handle:
        writer = csv.writer(handle)
        writer.writerow(['id', 'section', 'type', 'priority', 'source_en', 'translation_de'])
        for entry in strings:
            writer.writerow([
                string_id(entry.msgid), entry.section, entry.kind,
                entry.priority, entry.msgid, entry.msgstr,
            ])

    strings_name = os.path.basename(base) + '-STRINGS.md'
    brief = read_brief(len(strings), strings_name)

    brief_path = base + '-BRIEF.md'
    strings_path = os.path.join(out_dir, strings_name)
    with open(brief_path, 'w', encoding='utf-8') as handle:
        handle.write(brief)
    with open(strings_path, 'w', encoding='utf-8') as handle:
        handle.write(render_markdown(strings, os.path.basename(brief_path)))

    written = [brief_path, strings_path, base + '.csv', base + '.po']

    words = sum(len(entry.msgid.split()) for entry in strings)
    characters = sum(len(entry.msgid) for entry in strings)
    note = ' (changelog excluded: %d strings, --with-changelog to keep)' % dropped if dropped else ''
    print('%d strings, %d words, %d characters (%.1f normostran)%s\n%s'
          % (len(strings), words, characters, characters / 1800.0, note,
             '\n'.join('  ' + path for path in written)))


def read_brief(count, strings_name):
    """The brief, with its placeholders filled in - both change per build."""
    with open(os.path.join(HERE, 'TRANSLATOR-BRIEF.md'), encoding='utf-8') as handle:
        text = handle.read()
    return text.replace('{count}', str(count)).replace('{strings_file}', strings_name)


def worksheet_intro(count, brief_name):
    return [
        '# STRINGS TO TRANSLATE - captchaapi.eu readme, English → German (de_DE)',
        '',
        'Read **`%s`** first - it holds the register, the glossary and the terms that'
        % brief_name,
        'must stay in English. This file is the one that comes back.',
        '',
        'Type the German after `DE:` under each string. Keep the `[id]` markers - they',
        'are how your text finds its way back to the right string in the WordPress.org',
        'system. Do not reorder, merge, split or delete strings.',
        '',
        '%d strings, in the order a reader meets them on the page.' % count,
        '',
    ]


def render_markdown(strings, brief_name):
    lines = worksheet_intro(len(strings), brief_name)
    current = None
    for entry in strings:
        if entry.section != current:
            current = entry.section
            lines += ['---', '', '## %s' % SECTION_LABEL.get(current, current), '']
        flag = ' - **high priority**' if entry.priority == 'high' else ''
        lines += [
            '### `[%s]` %s%s' % (string_id(entry.msgid), entry.kind or 'text', flag),
            '',
            'EN: %s' % entry.msgid,
            '',
            'DE: ',
            '',
        ]
    return '\n'.join(lines)


if __name__ == '__main__':
    main()
