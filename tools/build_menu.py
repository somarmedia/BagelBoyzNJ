# -*- coding: utf-8 -*-
"""
Build data/menu.php from the Bagel Boyz POS export.

    python tools/build_menu.py

Reads menuprg/_menu_data.json (extracted from "BAGEL BOYZ.prg") and writes
data/menu.php in the format the ordering system expects. Names are cleaned for
customers; every price comes from the register.

Design: each POS "condiment group" becomes one reusable modifier group. An
item references the same groups the POS assigned it, so conditional pricing
(lettuce free on a breakfast sandwich, +$0.50 on a deli sandwich) is preserved
exactly as the shop rings it up.
"""
import json, re, io, os, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from pos_curation import clean_option, clean_bagel_option, titleize, BAGEL_TYPES

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA = json.load(io.open(os.path.join(ROOT, 'menuprg/_menu_data.json'), encoding='utf-8'))
CGS = DATA['condiment_groups']          # num -> {name, members:[{name,price,code}]}
ITEMS = DATA['items']

# Fast lookup: POS item name -> first sane record (price in cents).
BY_NAME = {}
for it in ITEMS:
    if it['only_cond']:
        continue
    BY_NAME.setdefault(it['name'].strip().upper(), it)


def price_of(pos_name, override=None):
    if override is not None:
        return override
    rec = BY_NAME.get(pos_name.strip().upper())
    return rec['price'] if rec else None


# =====================================================================
# MODIFIER GROUPS  — built from POS condiment groups
# =====================================================================
# Each entry: id -> dict(name, cg, min, max, mode?, only?, rename?, drop?)
#   cg      source condiment-group number
#   min/max choose-count rules for the storefront
#   only    keep only options whose cleaned name is in this set (order preserved)
#   rename  {cleaned_name: better_name}
#   drop    cleaned names to remove
#   clean   'bagel' to use bagel-abbreviation expansion
MOD_DEFS = {
    'bagel_type': dict(name='Choose Your Bagel', cg='1', min=1, max=1, clean='bagel',
        only=['Plain','Everything','Egg','Egg Everything','Cinnamon Raisin','Sesame',
              'Salt','Poppy','Onion','Multigrain','Multigrain Everything','Pumpernickel',
              'Pumpernickel Everything','Garlic','Rainbow','Blueberry','Gluten Free']),
    'specialty_bagel': dict(name='Choose Your Bagel', cg='18', min=1, max=1, clean='bagel',
        rename={'Ft':'French Toast'}),
    'bread_choice': dict(name='Make It a Different Bread', cg='3', min=0, max=1,
        rename={'Roll': 'Kaiser Roll'}),
    'wrap_choice': dict(name='Choose Your Wrap', cg='10', min=1, max=1,
        rename={'Roll': 'Kaiser Roll'}),
    'milk_choice': dict(name='Choose Your Milk', cg='17', min=0, max=1,
        drop=['Whipped Cream']),
    'coffee_syrup': dict(name='Add a Flavor Shot', cg='14', min=0, max=0),
    'egg_style': dict(name='Egg Style', cg='4', min=0, max=1,
        drop=['Well Done-kitch','Medium','Over Medium','Over Easy','Sunnyside Up','Hard']),
    'cheese_type': dict(name='Choose Your Cheese', cg='6', min=1, max=1,
        drop=['Extra Cheese']),
    'add_cheese': dict(name='Add Cheese', cg='22', min=0, max=1),
    'meat_choice': dict(name='Choose Your Meat', cg='8', min=1, max=1),
    'add_meat': dict(name='Add Extra Meat', cg='20', min=0, max=0),
    'gourmet_cc': dict(name='Choose Your Gourmet Cream Cheese', cg='5', min=1, max=1),
    'sandwich_extras': dict(name='Sandwich Extras', cg='12', min=0, max=0,
        drop=['Cold','Hot','Home Fries','No Home Fries']),
    'cc_condiments': dict(name='Add Toppings', cg='23', min=0, max=0),
    'prep': dict(name='Condiments & Prep', cg='2', min=0, max=0,
        drop=['No Home Fries','Well Done-','Well Done Bacon','Crispy Bacon']),
    'omelet_side': dict(name='Served With', cg='21', min=0, max=1),
    'lox_toppings': dict(name='Add Toppings', cg='28', min=0, max=0),
    'pastrami_temp': dict(name='Hot or Cold', cg='24', min=1, max=1),
    'wrap_protein': dict(name='Choose Your Protein', cg='26', min=1, max=1),
    'chicken_or_turkey': dict(name='Choose Your Protein', cg='31', min=1, max=1),
    'hoagie_meat': dict(name='Choose Your Meat', cg='32', min=1, max=1),
    'hoagie_extras': dict(name='Add Toppings', cg='33', min=0, max=0),
}


def build_modifier_group(gid, spec):
    cg = CGS.get(spec['cg'])
    if not cg:
        return None
    cleaner = clean_bagel_option if spec.get('clean') == 'bagel' else clean_option
    seen, options = set(), []
    only = spec.get('only')
    only_lower = [o.lower() for o in only] if only else None
    drop = set(d.lower() for d in spec.get('drop', []))
    rename = spec.get('rename', {})

    # Collect cleaned members (dedupe by name, keep lowest nonneg price).
    members = {}
    order = []
    for m in cg['members']:
        nm = cleaner(m['name'])
        nm = rename.get(nm, nm)
        if not nm or nm.lower() in drop:
            continue
        if nm not in members:
            members[nm] = m['price']
            order.append(nm)
        else:
            members[nm] = min(members[nm], m['price'])

    if only_lower is not None:
        # Emit in the curated order, pulling price from POS where present.
        for want, wl in zip(only, only_lower):
            for nm in order:
                if nm.lower() == wl:
                    options.append((nm, members[nm]))
                    break
    else:
        for nm in order:
            options.append((nm, members[nm]))

    if not options:
        return None
    return {
        'name': spec['name'], 'min': spec['min'], 'max': spec['max'],
        'options': [{'id': slug(nm), 'name': nm, 'price': pr} for nm, pr in options],
    }


def slug(s):
    s = re.sub(r'[^a-z0-9]+', '_', s.lower()).strip('_')
    return s or 'opt'


# Map a POS condiment-group number to one of our modifier group ids.
CG_TO_GROUP = {spec['cg']: gid for gid, spec in MOD_DEFS.items()}
# CGs we intentionally ignore on items (handled by the item itself or noise).
CG_IGNORE = {'7', '9', '11', '13', '19', '25', '29'}


def item_groups(rec):
    """Ordered, de-duplicated modifier group ids for a POS item."""
    out = []
    for r in rec['cond_refs']:
        gid = CG_TO_GROUP.get(r['cg'])
        if gid and gid not in out:
            out.append(gid)
    return out


# =====================================================================
# JUNK FILTER
# =====================================================================
def is_junk(rec):
    nm = rec['name'].strip()
    if not nm or nm.upper().startswith('PLU '):
        return True
    if re.search(r'\*|\^', nm):                 # cashier-only decorated rows
        return True
    if rec['price'] <= 0:
        return True
    if rec['price'] > 13000:                     # mis-keyed ($155 corned beef, etc.)
        return True
    # Snack/misc misfiled into food groups.
    if re.search(r'\bOZ\b|CHIPS|RUFFLES|DIRTY|LAND O LAKES|1/2 & 1/2', nm, re.I):
        return True
    return False


# =====================================================================
# DESCRIPTIONS & POPULAR FLAGS  (curated, keyed by cleaned display name)
# =====================================================================
DESCRIPTIONS = {
    'Bacon, Egg & Cheese': 'The classic BEC. Ask for it SPK.',
    'Taylor Ham, Egg & Cheese': 'The Jersey staple (a.k.a. pork roll).',
    'Big Rob "G" Belly Buster': 'Eggs with pork roll, bacon, sausage, hash brown & cheese.',
    'RJ Special': 'Egg whites, turkey, pepper jack, bacon & spinach with chipotle mayo and hot sauce.',
    'Healthy Turkey': 'Egg whites, Oven Gold Turkey, spinach & choice of cheese.',
    'Healthy Chicken': 'Egg whites, EverRoast Chicken, spinach & avocado.',
    'Bagel Boyz Sloppy': 'Grilled Oven Gold Turkey & roast beef with cole slaw & Russian dressing.',
    'Kevin\'s Special': 'Grilled chicken cutlet, bacon, pepper jack, a fried egg & jalapeños with chipotle mayo.',
    'Grand Supreme': 'Chicken, turkey, salami, pepperoni, ham, bacon, lettuce, tomato, onions & balsamic.',
    'B.h. Stuffed Hoagie': "Boar's Head stuffed hoagie — pick your meat & cheese.",
    'Cold Brew': 'Small-batch, slow-steeped. Smooth and strong.',
}
POPULAR = {
    'Bacon, Egg & Cheese', 'Taylor Ham, Egg & Cheese', 'Bagel with Cream Cheese',
    'Bagel Boyz Cheesesteak', 'Tuna Salad Sandwich', 'Latte', 'Cold Brew',
    'Bagel Boyz Sloppy', 'Oven Gold Turkey Sandwich',
}


def desc_for(name):
    return DESCRIPTIONS.get(name, '')


# =====================================================================
# FOOD CATEGORIES  (source group -> curated category)
# =====================================================================
CATS = []          # each: dict(id,name,icon,desc,tax_exempt,items[])


def food_category(cid, name, icon, desc, group, tax_exempt=False,
                  name_over=None, force_groups=None, skip=None):
    name_over = name_over or {}
    skip = set(s.upper() for s in (skip or []))
    seen_names = set()
    items = []
    for rec in ITEMS:
        if rec['group'] != group or rec['only_cond']:
            continue
        if rec['name'].strip().upper() in skip:
            continue
        if is_junk(rec):
            continue
        disp = name_over.get(rec['name'].strip().upper()) or titleize(rec['name'])
        if disp in seen_names:                 # de-dupe repeated POS rows
            continue
        seen_names.add(disp)
        groups = item_groups(rec)
        if force_groups:
            groups = force_groups(disp, groups)
        items.append({
            'id': slug(disp), 'name': disp, 'price': rec['price'],
            'desc': desc_for(disp), 'popular': disp in POPULAR, 'groups': groups,
        })
    CATS.append(dict(id=cid, name=name, icon=icon, desc=desc,
                     tax_exempt=tax_exempt, items=items))
    return items


# =====================================================================
# COFFEE  — collapse size-encoded PLUs into one drink each
# =====================================================================
SIZE_LABEL = {8: '8 oz', 11: '11 oz', 12: '12 oz (Small)', 14: '14 oz',
              16: '16 oz (Medium)', 20: '20 oz (Large)', 24: '24 oz', 6: '6 oz'}
DYNAMIC_SIZE_GROUPS = {}     # gid -> group dict (per-drink)

# A group-9 item is a made-to-order coffee-bar drink only if its name contains
# one of these. Everything else in group 9 (Tropicana, Gatorade, Nesquik, cans,
# bottles…) is a packaged drink and belongs in Cold Drinks.
COFFEE_WORDS = re.compile(
    r'LATTE|MATCHA|CAPPU|CAPUC|MOCHA|MACCHIATO|AMERICANO|COLD ?BREW|ESPRESSO|'
    r'FLAT WHITE|HOT CHOCOLATE|REFRESHER|BOX OF JOE|\bCOFFEE\b|\bTEA\b|CORTADO|'
    r'FRAPP', re.I)


def nice_drink(name):
    """Tidy a coffee/drink display name: fix -hot/-iced suffix and spelling."""
    n = titleize(name)
    n = re.sub(r'\s*[-–]\s*hot\b', ' (Hot)', n, flags=re.I)
    n = re.sub(r'\s*[-–]\s*iced\b', ' (Iced)', n, flags=re.I)
    n = re.sub(r'\bCappu?c?ino\b', 'Cappuccino', n, flags=re.I)
    n = re.sub(r'\bCapucino\b', 'Cappuccino', n, flags=re.I)
    n = n.replace('Smores', "S'mores").replace('Cinn ', 'Cinnamon ')
    return n.strip()


def is_coffee_bar(name):
    return bool(COFFEE_WORDS.search(name))


def coffee_categories():
    size_re = re.compile(r'^\s*(\d+)\s*OZ\.?\s*(.*)$', re.I)
    drinks, order, bottled = {}, [], []

    def add_sized(base, sz, price):
        key = base.upper()
        if key not in drinks:
            drinks[key] = {'disp': nice_drink(base), 'sizes': {}}
            order.append(key)
        drinks[key]['sizes'][sz] = price

    for rec in ITEMS:
        if rec['group'] != 9 or rec['only_cond']:
            continue
        nm = rec['name'].strip()
        if re.search(r'[*^]', nm) or rec['price'] <= 0 or nm.upper() == 'ADD FLAVOR':
            continue
        m = size_re.match(nm)
        mm = re.match(r'^(SM|MED|LG)\.?\s+(.*)$', nm, re.I)
        if m:
            sz, base = int(m.group(1)), m.group(2).strip()
            if is_coffee_bar(base):
                add_sized(base, sz, rec['price'])
            else:
                bottled.append((nice_drink(nm), rec['price']))
        elif mm:
            sz = {'SM': 12, 'MED': 16, 'LG': 20}[mm.group(1).upper()]
            add_sized(mm.group(2).strip(), sz, rec['price'])
        elif re.search(r'ESPRESSO|BOX OF JOE', nm, re.I) and is_coffee_bar(nm):
            k = nm.upper()
            if k not in drinks:
                drinks[k] = {'disp': nice_drink(nm), 'sizes': {0: rec['price']}}
                order.append(k)
        else:
            bottled.append((nice_drink(nm), rec['price']))

    hot, iced = [], []
    for key in order:
        d = drinks[key]
        sizes = sorted(d['sizes'].items())
        item = {'id': slug(d['disp']), 'name': d['disp'], 'price': sizes[0][1],
                'desc': desc_for(d['disp']), 'popular': d['disp'] in POPULAR, 'groups': []}
        if len(sizes) > 1:
            gid = 'size_' + item['id']
            DYNAMIC_SIZE_GROUPS[gid] = {
                'name': 'Size', 'min': 1, 'max': 1, 'mode': 'variant',
                'options': [{'id': 'sz%d' % sz, 'name': SIZE_LABEL.get(sz, '%d oz' % sz), 'price': pr}
                            for sz, pr in sizes],
            }
            item['groups'].append(gid)
        low = d['disp'].lower()
        if any(w in low for w in ('latte', 'cappuc', 'matcha', 'mocha', 'macchiato',
                                  'americano', 'coffee', 'cold brew', 'flat white')):
            item['groups'] += ['milk_choice', 'coffee_syrup']
        (iced if ('iced' in low or 'cold brew' in low or 'refresher' in low) else hot).append(item)

    if hot:
        CATS.append(dict(id='coffee', name='Coffee & Espresso', icon='☕',
                         desc='Hand-crafted hot drinks.', tax_exempt=False, items=hot))
    if iced:
        CATS.append(dict(id='iced', name='Iced & Cold', icon='\U0001F9CA',
                         desc='Iced lattes, cold brew, matcha & refreshers.',
                         tax_exempt=False, items=iced))
    return bottled


# =====================================================================
# SPREADS BY THE POUND  (group 14 — has real prices)
# =====================================================================
def spreads_by_pound():
    items, seen = [], set()
    for rec in ITEMS:
        if rec['group'] != 14 or rec['only_cond'] or is_junk(rec):
            continue
        disp = titleize(rec['name'])
        if disp in seen:
            continue
        seen.add(disp)
        groups = ['gourmet_cc'] if '5' in [r['cg'] for r in rec['cond_refs']] else []
        items.append({'id': slug(disp), 'name': disp, 'price': rec['price'],
                      'desc': '', 'popular': False, 'groups': groups})
    if items:
        CATS.append(dict(id='by_pound', name='Cream Cheese & Spreads by the Pound',
                         icon='\U0001F9C0', desc='Take home a tub. Great for a crowd.',
                         tax_exempt=False, items=items))


# =====================================================================
# GRAB & GO  (groups 10 + 15 snacks + bottled drinks from coffee)
# =====================================================================
def snack_name(nm):
    n = re.sub(r'\s+', ' ', nm.strip())
    n = re.sub(r'^\d+(\.\d+)?\s*OZ\.?\s*', '', n, flags=re.I)
    n = re.sub(r'^\d+\s*\d*/\d+\s*OZ\.?\s*', '', n, flags=re.I)
    return titleize(n)


def grab_and_go(bottled):
    snacks, seen = [], set()
    for rec in ITEMS:
        if rec['group'] not in (10, 15) or rec['only_cond']:
            continue
        nm = rec['name'].strip()
        if re.search(r'[*^]', nm) or rec['price'] <= 0 or rec['price'] > 3000:
            continue
        disp = snack_name(nm)
        if not disp or disp in seen:
            continue
        seen.add(disp)
        snacks.append({'id': (slug(disp)[:40] or 'snack'), 'name': disp,
                       'price': rec['price'], 'desc': '', 'popular': False, 'groups': []})

    dseen, dclean = set(), []
    for nm, pr in bottled:
        if nm in dseen or pr <= 0:
            continue
        dseen.add(nm)
        dclean.append({'id': (slug(nm)[:40] or 'drink'), 'name': nm, 'price': pr,
                       'desc': '', 'popular': False, 'groups': []})
    if dclean:
        CATS.append(dict(id='drinks', name='Cold Drinks', icon='\U0001F964',
                         desc='Soda, water, juice & bottled iced coffee.',
                         tax_exempt=False, items=dclean))
    if snacks:
        CATS.append(dict(id='grab_go', name='Grab & Go', icon='\U0001F36B',
                         desc='Chips, candy, gum & snacks.', tax_exempt=False, items=snacks))


# =====================================================================
# ID UNIQUENESS  — item ids and group ids must be globally unique
# =====================================================================
def dedupe_ids():
    used = set()
    for cat in CATS:
        for it in cat['items']:
            base = it['id'] or 'item'
            cid = base
            n = 2
            while cid in used:
                cid = '%s_%d' % (base, n)
                n += 1
            used.add(cid)
            it['id'] = cid


# =====================================================================
# PHP EMISSION
# =====================================================================
def php_str(s):
    return "'" + str(s).replace('\\', '\\\\').replace("'", "\\'") + "'"


def emit_group(gid, g, indent):
    pad = ' ' * indent
    lines = ["%s%s => [" % (pad, php_str(gid))]
    head = "%s    'name' => %s, 'min' => %d, 'max' => %d," % (
        pad, php_str(g['name']), g['min'], g['max'])
    if g.get('mode') == 'variant':
        head += " 'mode' => 'variant',"
    lines.append(head)
    lines.append("%s    'options' => [" % pad)
    for o in g['options']:
        lines.append("%s        ['id' => %s, 'name' => %s, 'price' => %d]," % (
            pad, php_str(o['id']), php_str(o['name']), o['price']))
    lines.append("%s    ]," % pad)
    lines.append("%s]," % pad)
    return "\n".join(lines)


def emit_php(groups, cats):
    L = []
    L.append('<?php')
    L.append('/**')
    L.append(' * Bagel Boyz NJ — Canonical Online Ordering Menu  (v2)')
    L.append(' * =====================================================')
    L.append(' * GENERATED from the register export by tools/build_menu.py.')
    L.append(' * Do not hand-edit — re-run the generator. Names cleaned for')
    L.append(' * customers; every price comes straight from the POS.')
    L.append(' *')
    L.append(' * THIS FILE IS THE SINGLE SOURCE OF TRUTH FOR PRICING. The server')
    L.append(' * re-prices every order from it; the browser never sets a price.')
    L.append(' * ALL MONEY IS IN INTEGER CENTS.')
    L.append(' */')
    L.append('return [')
    L.append('')
    L.append('    /* ============ MODIFIER GROUPS ============ */')
    L.append("    'modifier_groups' => [")
    for gid, g in groups.items():
        L.append(emit_group(gid, g, 8))
    L.append('    ],')
    L.append('')
    L.append('    /* ============ CATEGORIES ============ */')
    L.append("    'categories' => [")
    for cat in cats:
        if not cat['items']:
            continue
        L.append('        [')
        L.append("            'id' => %s, 'name' => %s, 'icon' => %s," % (
            php_str(cat['id']), php_str(cat['name']), php_str(cat['icon'])))
        if cat.get('desc'):
            L.append("            'desc' => %s," % php_str(cat['desc']))
        if cat.get('tax_exempt'):
            L.append("            'tax_exempt' => true,")
        L.append("            'items' => [")
        for it in cat['items']:
            parts = ["'id' => %s" % php_str(it['id']),
                     "'name' => %s" % php_str(it['name']),
                     "'price' => %d" % it['price']]
            if it.get('desc'):
                parts.append("'desc' => %s" % php_str(it['desc']))
            if it.get('popular'):
                parts.append("'popular' => true")
            if it.get('groups'):
                parts.append("'groups' => [%s]" % ', '.join(php_str(x) for x in it['groups']))
            L.append("                [%s]," % ', '.join(parts))
        L.append('            ],')
        L.append('        ],')
    L.append('    ],')
    L.append('];')
    return "\n".join(L) + "\n"


# =====================================================================
# MAIN
# =====================================================================
ALL_GROUPS = {}


def main():
    global ALL_GROUPS
    # 1. Build reusable modifier groups from POS condiment groups.
    for gid, spec in MOD_DEFS.items():
        g = build_modifier_group(gid, spec)
        if g:
            ALL_GROUPS[gid] = g

    # 2. Food categories (source POS group -> curated category).
    food_category('bagels', 'Bagels & Spreads', '\U0001F96F',
                  'Boiled & baked fresh daily, the NJ way.', 1, tax_exempt=True)
    food_category('breakfast', 'Breakfast Sandwiches', '\U0001F373',
                  'On your choice of bagel. Roll or wrap available.', 2)
    food_category('omelets', 'Omelets & Platters', '\U0001F958',
                  'Served with home fries and choice of toast or bagel.', 3)
    food_category('specialty', 'Specialty Sandwiches', '⭐',
                  'The house favorites. All $9.99.', 4)
    food_category('wraps', 'Wraps', '\U0001F32F', 'All wraps $9.99.', 5)
    food_category('deli', "Boar's Head Deli", '\U0001F96A',
                  'Served on your choice of bagel or bread.', 6)
    food_category('salads', 'Salad Sandwiches', '\U0001F957',
                  'Egg, tuna, chicken & whitefish salad.', 7)
    food_category('sides', 'Sides', '\U0001F35F', '', 8)
    food_category('bakery', 'Bakery', '\U0001F9C1',
                  'Muffins, cookies & fresh-baked treats.', 11)

    # 3. Coffee + iced (returns bottled drinks for grab & go).
    bottled = coffee_categories()

    # 4. By-the-pound spreads.
    spreads_by_pound()

    # 5. Grab & go + cold drinks.
    grab_and_go(bottled)

    # 6. Merge dynamic per-drink size groups.
    ALL_GROUPS.update(DYNAMIC_SIZE_GROUPS)

    # 7. Globally unique ids.
    dedupe_ids()

    # 8. Emit.
    out = emit_php(ALL_GROUPS, CATS)
    path = os.path.join(ROOT, 'data', 'menu.php')
    io.open(path, 'w', encoding='utf-8').write(out)

    items = sum(len(c['items']) for c in CATS)
    print('wrote %s' % path)
    print('  categories: %d' % len([c for c in CATS if c['items']]))
    print('  items     : %d' % items)
    print('  mod groups: %d (%d static + %d per-drink sizes)' % (
        len(ALL_GROUPS), len(ALL_GROUPS) - len(DYNAMIC_SIZE_GROUPS), len(DYNAMIC_SIZE_GROUPS)))
    for c in CATS:
        if c['items']:
            print('    %-16s %3d' % (c['id'], len(c['items'])))


if __name__ == '__main__':
    main()
