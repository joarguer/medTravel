#!/usr/bin/env python3
import json,os

ROOT=os.getcwd()
INP=os.path.join('.cache','link_report.json')
OUT=os.path.join('.cache','link_report_filtered.json')

if not os.path.exists(INP):
    print('ERROR: input missing',INP); raise SystemExit(1)

raw=json.load(open(INP,'r',encoding='utf-8'))

excluded_file_prefixes=('assets/',)
exclude_link_prefixes=('#','mailto:','http://','https://','//')

root_broken=[]
docs_broken=[]
md_internal_broken=[]
filtered_by_source={}

for src,entry in sorted(raw.items()):
    if any(src.startswith(p) for p in excluded_file_prefixes):
        continue
    broken=entry.get('broken',[])
    kept=[]
    for b in broken:
        link=b.get('link')
        if not link: continue
        lp=link.strip()
        if any(lp.lower().startswith(p) for p in exclude_link_prefixes):
            continue
        expected=b.get('expected')
        if not expected: continue
        norm_expected=os.path.normpath(expected)
        # root of project
        if os.path.sep not in norm_expected:
            root_broken.append({'source':src,'link':link,'expected':expected})
        # docs/
        if norm_expected.startswith('docs'+os.path.sep):
            docs_broken.append({'source':src,'link':link,'expected':expected})
        # internal md
        if norm_expected.lower().endswith('.md'):
            md_internal_broken.append({'source':src,'link':link,'expected':expected})
        kept.append({'link':link,'expected':expected})
    if kept:
        filtered_by_source[src]={'broken':kept}

def uniq(lst):
    seen=set(); out=[]
    for d in lst:
        t=(d['source'],d['link'],d['expected'])
        if t in seen: continue
        seen.add(t); out.append(d)
    return out

root_broken=uniq(root_broken)
docs_broken=uniq(docs_broken)
md_internal_broken=uniq(md_internal_broken)

summary={
    'counts':{
        'sources_with_broken': len(filtered_by_source),
        'root_broken': len(root_broken),
        'docs_broken': len(docs_broken),
        'md_internal_broken': len(md_internal_broken)
    },
    'root_broken': root_broken,
    'docs_broken': docs_broken,
    'md_internal_broken': md_internal_broken,
    'by_source': filtered_by_source
}

with open(OUT,'w',encoding='utf-8') as fh:
    json.dump(summary,fh,indent=2,ensure_ascii=False)

print('WROTE',OUT)
print('Counts:',summary['counts'])
