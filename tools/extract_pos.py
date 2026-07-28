# -*- coding: utf-8 -*-
"""
Extract the Bagel Boyz POS register export into a compact JSON the menu
generator can consume.

    python tools/extract_pos.py

Reads menuprg/BAGEL BOYZ.prg (a SAM4S/CAS HX-7500 PLU export, ~19 MB) and
writes menuprg/_menu_data.json holding:
  - condiment_groups : {num: {name, members:[{code,name,price}]}}
  - items            : [{code,name,price,group,category,cond_refs,only_cond}]

Prices are integer cents. Run tools/build_menu.py afterwards to produce
data/menu.php.
"""
import xml.etree.ElementTree as ET
import json, os, io, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, 'menuprg', 'BAGEL BOYZ.prg')
OUT = os.path.join(ROOT, 'menuprg', '_menu_data.json')

if not os.path.exists(SRC):
    sys.exit('POS export not found at %s — drop the register file there first.' % SRC)

root = ET.parse(SRC).getroot()
plus = root.findall('.//plu')


def txt(el, tag, default=''):
    c = el.find(tag)
    return c.text if c is not None and c.text else default


name_by_code = {p.get('code'): p.get('name', '').strip() for p in plus}
price_by_code = {p.get('code'): int((p.findall('price')[0].text
                 if p.findall('price') else 0) or 0) for p in plus}

# Condiment groups: num -> {name, members}
cgs = {}
cgl = root.find('.//condiment_group_list')
if cgl is not None:
    for cg in cgl.findall('condiment_group'):
        num = cg.get('num')
        members = []
        for c in cg.findall('cond_plu_code'):
            code = c.text
            if code and code.strip('0'):
                members.append({'code': code,
                                'name': name_by_code.get(code, '?'),
                                'price': price_by_code.get(code, 0)})
        if members:
            cgs[num] = {'name': cg.get('name', ''), 'members': members}

# Items
items = []
for p in plus:
    refs = []
    for c in p.findall('condiment'):
        if c.get('en') == '1':
            cg = txt(c, 'cond_group')
            q = c.find('quantity')
            if cg and cg != '0':
                refs.append({'cg': cg,
                             'min': int(q.get('min')) if q is not None else 0,
                             'max': int(q.get('max')) if q is not None else 0})
    oc = p.find('only_sold_as_cond')
    prices = p.findall('price')
    items.append({
        'code': p.get('code'),
        'name': p.get('name', '').strip(),
        'price': int((prices[0].text if prices else 0) or 0),
        'group': int(txt(p, 'group') or 0),
        'category': int(txt(p, 'category') or 0),
        'cond_refs': refs,
        'only_cond': oc is not None and oc.get('en') == '1',
    })

io.open(OUT, 'w', encoding='utf-8').write(json.dumps(
    {'condiment_groups': cgs, 'items': items}, indent=1))

print('wrote %s' % OUT)
print('  condiment groups: %d' % len(cgs))
print('  items           : %d (%d condiments)' %
      (len(items), sum(1 for i in items if i['only_cond'])))
