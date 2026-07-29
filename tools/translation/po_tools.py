"""Minimal PO reader/writer for the WP.org readme translation round-trip.

The readme project on translate.wordpress.org has no plural forms and no
contexts, so a small parser is enough - and it keeps msgid bytes untouched,
which is what makes the finished file importable again.
"""

import re

_ESCAPES = {'\\n': '\n', '\\t': '\t', '\\r': '\r', '\\"': '"', '\\\\': '\\'}
_UNESCAPE_RE = re.compile(r'\\[ntr"\\]')
_ESCAPE_RE = re.compile(r'([\\"])')


def unescape(value):
    return _UNESCAPE_RE.sub(lambda m: _ESCAPES[m.group(0)], value)


def escape(value):
    value = _ESCAPE_RE.sub(r'\\\1', value)
    return value.replace('\n', '\\n').replace('\t', '\\t').replace('\r', '\\r')


class Entry:
    def __init__(self):
        self.comments = []      # every leading #-line, verbatim
        self.msgid = ''
        self.msgstr = ''

    @property
    def is_header(self):
        return self.msgid == ''

    @property
    def section(self):
        """'faq', 'description', 'installation', ... from the #. comment."""
        for line in self.comments:
            m = re.match(r'#\.\s*Found in (\w+)', line)
            if m:
                return m.group(1)
            if 'Short description' in line:
                return 'short-description'
            if 'Plugin name' in line:
                return 'plugin-name'
        return 'other'

    @property
    def kind(self):
        """'paragraph', 'header', 'list item', ... from the #. comment."""
        for line in self.comments:
            m = re.match(r'#\.\s*Found in \w+ (.+?)\.?$', line)
            if m:
                return m.group(1)
        return ''

    @property
    def priority(self):
        for line in self.comments:
            m = re.search(r'gp-priority:\s*(\S+)', line)
            if m:
                return m.group(1)
        return 'normal'


def parse(path):
    """Return the list of entries, header first."""
    entries = []
    entry = Entry()
    target = None           # which field the following bare strings continue
    with open(path, encoding='utf-8') as handle:
        for raw in handle:
            line = raw.rstrip('\n')
            if not line.strip():
                if entry.msgid or entry.msgstr or entry.comments:
                    entries.append(entry)
                    entry = Entry()
                    target = None
                continue
            if line.startswith('#'):
                entry.comments.append(line)
                continue
            if line.startswith('msgid '):
                target = 'msgid'
                entry.msgid = unescape(line[6:].strip()[1:-1])
                continue
            if line.startswith('msgstr '):
                target = 'msgstr'
                entry.msgstr = unescape(line[7:].strip()[1:-1])
                continue
            if line.startswith('"') and target:
                setattr(entry, target, getattr(entry, target) + unescape(line.strip()[1:-1]))
    if entry.msgid or entry.msgstr or entry.comments:
        entries.append(entry)
    return entries


def write(path, entries):
    chunks = []
    for entry in entries:
        block = list(entry.comments)
        block.append('msgid "%s"' % escape(entry.msgid))
        block.append('msgstr "%s"' % escape(entry.msgstr))
        chunks.append('\n'.join(block))
    with open(path, 'w', encoding='utf-8') as handle:
        handle.write('\n\n'.join(chunks) + '\n')
