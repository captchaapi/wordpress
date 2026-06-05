#!/usr/bin/env python3
import json, os, subprocess, sys

BUILD = os.path.dirname(os.path.abspath(__file__))
LANG = os.path.dirname(BUILD)
POT = os.path.join(LANG, 'captchaapi.pot')

LOCALES = {
    'de': ('de_DE', 'nplurals=2; plural=(n != 1);'),
    'fr': ('fr_FR', 'nplurals=2; plural=(n > 1);'),
    'es': ('es_ES', 'nplurals=2; plural=(n != 1);'),
    'it': ('it_IT', 'nplurals=2; plural=(n != 1);'),
    'pl': ('pl_PL', 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : 2);'),
    'nl': ('nl_NL', 'nplurals=2; plural=(n != 1);'),
    'pt': ('pt_PT', 'nplurals=2; plural=(n != 1);'),
    'cs': ('cs_CZ', 'nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;'),
    'ro': ('ro_RO', 'nplurals=3; plural=(n==1 ? 0 : (n==0 || (n%100>0 && n%100<20)) ? 1 : 2);'),
}


def unescape(s):
    return s.replace('\\n', '\n').replace('\\t', '\t').replace('\\"', '"').replace('\\\\', '\\')


def escape(s):
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')


def extract_msgids(path):
    ids, cur, mode = [], '', None
    for raw in open(path, encoding='utf-8'):
        line = raw.rstrip('\n')
        if line.startswith('msgid '):
            mode, cur = 'id', unescape(line[6:].strip()[1:-1])
        elif line.startswith('msgstr'):
            if mode == 'id' and cur != '':
                ids.append(cur)
            mode = None
        elif line.startswith('"') and mode == 'id':
            cur += unescape(line.strip()[1:-1])
        else:
            mode = None
    return ids


def main():
    msgids = extract_msgids(POT)
    print(f'{len(msgids)} msgids in POT')

    for code, (locale, plural) in LOCALES.items():
        with open(os.path.join(BUILD, f'{code}.json'), encoding='utf-8') as f:
            trans = json.load(f)

        missing = [k for k in trans if k not in msgids]
        if missing:
            print(f'  WARNING {locale}: {len(missing)} keys not in POT', file=sys.stderr)
            for m in missing:
                print('    ' + m, file=sys.stderr)

        translated = sum(1 for m in msgids if trans.get(m))
        po_path = os.path.join(LANG, f'captchaapi-{locale}.po')
        mo_path = os.path.join(LANG, f'captchaapi-{locale}.mo')

        out = [
            'msgid ""', 'msgstr ""',
            '"Project-Id-Version: captchaapi 1.1.0\\n"',
            '"Report-Msgid-Bugs-To: https://captchaapi.eu\\n"',
            '"MIME-Version: 1.0\\n"',
            '"Content-Type: text/plain; charset=UTF-8\\n"',
            '"Content-Transfer-Encoding: 8bit\\n"',
            f'"Language: {locale}\\n"',
            f'"Plural-Forms: {plural}\\n"',
            '',
        ]
        for mid in msgids:
            val = trans.get(mid, '')
            out.append(f'msgid "{escape(mid)}"')
            out.append(f'msgstr "{escape(val)}"')
            out.append('')

        with open(po_path, 'w', encoding='utf-8') as f:
            f.write('\n'.join(out))

        subprocess.run(['msgfmt', '-o', mo_path, po_path], check=True)
        print(f'  {locale}: {translated}/{len(msgids)} translated -> {os.path.basename(mo_path)}')


if __name__ == '__main__':
    main()
