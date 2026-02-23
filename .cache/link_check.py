#!/usr/bin/env python3
import re,os,sys,json

def main():
    root = os.getcwd()
    md_files = []
    for dp,_,files in os.walk(root):
        for f in files:
            if f.lower().endswith('.md'):
                md_files.append(os.path.join(dp,f))

    pattern = re.compile(r'\[([^\]]+)\]\((?!https?://)([^)]+)\)')
    refdef = re.compile(r'^\s*\[([^\]]+)\]:\s*(?!https?://)(\S+)', re.M)
    report = {}

    for path in sorted(md_files):
        rel_path = os.path.relpath(path, root)
        try:
            with open(path, 'r', encoding='utf-8', errors='ignore') as fh:
                txt = fh.read()
        except Exception as e:
            report[rel_path] = {'error': str(e)}
            continue

        links = []
        for m in pattern.finditer(txt):
            links.append(m.group(2).strip())
        for m in refdef.finditer(txt):
            links.append(m.group(2).strip())

        valid = []
        broken = []
        suspicious = []

        for link in links:
            link_orig = link
            link = link.split('#',1)[0].split('?',1)[0]
            if not link:
                broken.append({'link': link_orig, 'reason': 'empty after stripping fragment'})
                continue
            if os.path.isabs(link):
                check = os.path.normpath(os.path.join(root, link.lstrip('/')))
            else:
                srcdir = os.path.dirname(path)
                check = os.path.normpath(os.path.join(srcdir, link))

            if os.path.exists(check) and os.path.isfile(check):
                valid.append({'link': link_orig, 'target': os.path.relpath(check, root)})
            else:
                basename = os.path.basename(link)
                matches = []
                for dp,_,files in os.walk(root):
                    if basename in files:
                        matches.append(os.path.relpath(os.path.join(dp, basename), root))
                if matches:
                    suspicious.append({'link': link_orig, 'expected': os.path.relpath(check, root), 'matches': sorted(matches)})
                else:
                    broken.append({'link': link_orig, 'expected': os.path.relpath(check, root)})

        report[rel_path] = {'valid': valid, 'broken': broken, 'suspicious': suspicious}

    print(json.dumps(report, indent=2, ensure_ascii=False))

if __name__ == '__main__':
    main()
