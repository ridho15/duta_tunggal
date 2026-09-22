#!/usr/bin/env python3
import subprocess, re
from collections import defaultdict

result = subprocess.run(
    ['grep', '-rn', 'navigationSort\|navigationGroup', 'app/Filament/Resources/', '--include=*.php'],
    capture_output=True, text=True, cwd='/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP'
)
lines = result.stdout.strip().split('\n')

file_data = defaultdict(dict)
for line in lines:
    m = re.match(r'(app/Filament/Resources/\S+\.php):(\d+):\s*protected static \?(?:string|int) \$(navigationGroup|navigationSort)\s*=\s*(.+);', line.strip())
    if m:
        f, lineno, key, val = m.groups()
        fname = f.split('/')[-1]
        val = val.strip().strip("'")
        file_data[fname][key] = val

group_sort = defaultdict(list)
for fname, d in file_data.items():
    g = d.get('navigationGroup', 'none')
    s = d.get('navigationSort', 'none')
    group_sort[g].append((s, fname))

print('=== Collisions within same group ===')
found = False
for g, items in sorted(group_sort.items()):
    sorts = [s for s, f in items if s != 'none']
    seen = set()
    dups = set()
    for s in sorts:
        if s in seen:
            dups.add(s)
        seen.add(s)
    if dups:
        found = True
        print(f'GROUP: {g}')
        for s, f in sorted(items):
            marker = ' <-- COLLISION' if s in dups else ''
            print(f'  {s:5} {f}{marker}')

if not found:
    print('None found — all groups have unique sort values!')

print('\n=== Full navigation map per group ===')
for g, items in sorted(group_sort.items()):
    print(f'\n{g}:')
    for s, f in sorted(items, key=lambda x: int(x[0]) if x[0].isdigit() else 999):
        print(f'  {s:5} {f}')
