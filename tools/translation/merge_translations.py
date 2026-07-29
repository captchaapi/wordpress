"""Fold a finished worksheet back into a PO that translate.wordpress.org accepts.

Accepts the Word document, the Markdown document or the CSV produced by
build_translator_doc.py, matches every translation to its original msgid by id,
and checks the things GlotPress will not check for us: HTML entities, URLs and
the 150-character limit on the short description.

Usage: python3 tools/translation/merge_translations.py <worksheet.docx|.md|.csv> [locale]
Output: tools/translation/out/captchaapi-readme-<locale>-translated.po
"""

import csv
import os
import re
import sys
import xml.etree.ElementTree as ElementTree
import zipfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import po_tools
from build_translator_doc import string_id

HERE = os.path.dirname(os.path.abspath(__file__))
SHORT_DESCRIPTION_LIMIT = 150

ENTITY_RE = re.compile(r'&[a-zA-Z]+;|&#\d+;')
URL_RE = re.compile(r'https?://[^\s<>"\']+')


def read_csv(path):
    with open(path, encoding='utf-8-sig', newline='') as handle:
        return {
            row['id'].strip(): row['translation_de'].strip()
            for row in csv.DictReader(handle)
            if row.get('id')
        }


ID_RE = re.compile(r'^\s*(?:###\s+)?`?\[([0-9a-f]{8})\]`?')


def read_lines(lines):
    """Shared by the Markdown and Word documents: an id line, then a DE: line."""
    translations = {}
    current = None
    for line in lines:
        heading = ID_RE.match(line)
        if heading:
            current = heading.group(1)
            continue
        if current and line.lstrip().startswith('DE:'):
            translations[current] = line.split('DE:', 1)[1].strip()
            current = None
    return translations


def read_markdown(path):
    with open(path, encoding='utf-8') as handle:
        return read_lines(handle)


def read_docx(path):
    """One line per Word paragraph, runs joined - enough to find id and DE: lines."""
    namespace = '{http://schemas.openxmlformats.org/wordprocessingml/2006/main}'
    with zipfile.ZipFile(path) as archive:
        root = ElementTree.fromstring(archive.read('word/document.xml'))
    lines = []
    for paragraph in root.iter(namespace + 'p'):
        text = ''.join(node.text or '' for node in paragraph.iter(namespace + 't'))
        lines.append(text)
    return read_lines(lines)


def check(entry, translation):
    """Return the problems that would survive an import unnoticed."""
    problems = []
    source = entry.msgid

    source_entities = sorted(ENTITY_RE.findall(source))
    target_entities = sorted(ENTITY_RE.findall(translation))
    if source_entities != target_entities:
        problems.append('HTML entities differ: %s vs %s' % (source_entities, target_entities))

    missing_urls = [url for url in URL_RE.findall(source) if url not in translation]
    if missing_urls:
        problems.append('missing URL(s): %s' % ', '.join(missing_urls))

    if entry.section == 'short-description' and len(translation) > SHORT_DESCRIPTION_LIMIT:
        problems.append('short description is %d characters, limit is %d'
                        % (len(translation), SHORT_DESCRIPTION_LIMIT))

    if source.endswith('?') and not translation.endswith('?'):
        problems.append('source is a question, translation does not end with "?"')

    return problems


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    worksheet = sys.argv[1]
    locale = sys.argv[2] if len(sys.argv) > 2 else 'de'

    readers = {'.md': read_markdown, '.docx': read_docx, '.csv': read_csv}
    extension = os.path.splitext(worksheet)[1].lower()
    if extension not in readers:
        sys.exit('Unsupported worksheet: %s (expected .docx, .md or .csv)' % worksheet)
    translations = readers[extension](worksheet)

    source = os.path.join(HERE, 'source', 'captchaapi-readme-%s.po' % locale)
    entries = po_tools.parse(source)

    filled, skipped, problems = 0, 0, []
    for entry in entries:
        if entry.is_header:
            continue
        translation = translations.get(string_id(entry.msgid), '')
        if not translation:
            skipped += 1
            continue
        entry.msgstr = translation
        filled += 1
        for problem in check(entry, translation):
            problems.append('[%s] %s\n    EN: %s\n    DE: %s'
                            % (string_id(entry.msgid), problem, entry.msgid[:70], translation[:70]))

    unknown = set(translations) - {string_id(e.msgid) for e in entries if not e.is_header}

    out = os.path.join(HERE, 'out', 'captchaapi-readme-%s-translated.po' % locale)
    po_tools.write(out, entries)

    print('translated: %d, still empty: %d' % (filled, skipped))
    if unknown:
        print('WARNING: %d id(s) in the worksheet match no string: %s'
              % (len(unknown), ', '.join(sorted(unknown))))
    if problems:
        print('\n%d string(s) need a look before importing:\n' % len(problems))
        print('\n'.join(problems))
    print('\nwrote %s' % out)


if __name__ == '__main__':
    main()
