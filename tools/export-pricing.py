"""
Regenerate data/pricing-template.csv from data/menu.php.

    python tools/export-pricing.py      (run from the repo root)

Emits an editable price sheet covering every item, every by-weight price and
every paid modifier option. The `source` column marks where each price came
from — anything tagged ASSUMED is a guess that needs a human to confirm it.

Re-run this any time the menu changes and you want a fresh sheet to review.
Purely a development tool; nothing on the website depends on it.
"""
import io, re, csv

SRC = 'data/menu.php'
OUT = 'data/pricing-template.csv'

src = io.open(SRC, encoding='utf-8').read()
mg_block = src[src.index("'modifier_groups' => ["):src.index("'categories' => [")]
cat_block = src[src.index("'categories' => ["):]


def money(cents):
    return '%.2f' % (int(cents) / 100.0)


# ── Where each price came from ────────────────────────────────────────────
# menu     = printed on the current menu.php, taken verbatim
# derived  = calculated from a note on the menu ("roll or wrap add $0.75")
# ASSUMED  = I made it up; the menu doesn't say. THESE NEED CHECKING.
ASSUMED_ITEMS = {
    'bev_soda':          'Menu says "Varies" — I guessed $2.50',
    'bev_juice':         'Menu says "Varies" — I guessed $3.00',
    'bev_bottled_water': 'Menu says "Varies" — I guessed $2.00',
    'side_hash_brown':   'Priced from the Extras section, not listed under Sides',
}

ASSUMED_GROUPS = {
    'cheese_choice':       'Cheese varieties are not listed on the menu — confirm what you stock',
    'cheese_optional':     'Cheese varieties + $0.75 upcharge from Extras — confirm',
    'cream_cheese_flavor': 'Flavors are not listed on the menu — confirm what you stock',
    'condiments':          'Not listed on the menu — confirm the list',
    'coffee_prep':         'Not listed on the menu — confirm the list',
    'hot_bev_kind':        'Drink list from the Beverages line — confirm',
}

DERIVED_GROUPS = {
    'bread_choice':     'Menu: "Roll or wrap add $0.75"',
    'deli_bread':       'Menu: "Roll or wrap add $0.75"',
    'salad_bread':      'Menu: "Roll or wrap add $0.75"',
    'bagel_type':       'Gluten Free derived from $3.50 GF bagel vs $1.50 plain',
    'bagel_type_only':  'Gluten Free derived from $3.50 GF bagel vs $1.50 plain',
    'deli_bagel_type':  'Gluten Free derived from $3.50 GF bagel vs $1.50 plain',
    'salad_bagel_type': 'Gluten Free derived from $3.50 GF bagel vs $1.50 plain',
    'meat_choice':      'Menu: "turkey bacon add $0.99"',
    'egg_style':        'Menu: "Egg whites add $0.99"',
    'add_ons':          'Prices taken from the Extras section of the menu',
}

rows = []

# ── Items ─────────────────────────────────────────────────────────────────
# Category names may be double-quoted when they contain an apostrophe
# ("Boar's Head Deli"), so accept either quote style.
cat_re = re.compile(
    r"'id' => '([a-z0-9_]+)', 'name' => (\"[^\"]*\"|'[^']*'), 'icon'", re.S)

# Split the categories block into per-category chunks.
cat_starts = [(m.start(), m.group(1), m.group(2)[1:-1]) for m in cat_re.finditer(cat_block)]

for idx, (pos, cid, cname) in enumerate(cat_starts):
    end = cat_starts[idx + 1][0] if idx + 1 < len(cat_starts) else len(cat_block)
    chunk = cat_block[pos:end]

    item_re = re.compile(
        r"\['id' => '([a-z0-9_]+)',\s*'name' => (\"[^\"]*\"|'(?:[^'\\]|\\.)*'),\s*'price' => (\d+)")
    matches = list(item_re.finditer(chunk))

    for i, m in enumerate(matches):
        iid, iname, price = m.group(1), m.group(2)[1:-1].replace("\\'", "'"), m.group(3)
        note = ASSUMED_ITEMS.get(iid, '')
        rows.append({
            'type': 'ITEM',
            'section': cname,
            'id': iid,
            'name': iname,
            'current_price': money(price),
            'NEW_PRICE': '',
            'source': 'ASSUMED' if note else 'menu',
            'check_this': note,
        })

        # Deli / salad items also carry a per-pound (or half-pound) price in
        # variant_prices. Those are real menu prices and must be editable too,
        # so emit a row for every variant other than the base 'sandwich' one.
        item_end = matches[i + 1].start() if i + 1 < len(matches) else len(chunk)
        vp = re.search(r"'variant_prices' => \[(.*?)\]", chunk[m.start():item_end], re.S)
        if vp:
            for vm in re.finditer(r"'([a-z0-9_]+)' => (\d+)", vp.group(1)):
                vid, vprice = vm.group(1), vm.group(2)
                if vid == 'sandwich':
                    continue          # same as the item row above
                label = {'per_lb': 'per pound', 'half_lb': 'per ½ pound'}.get(vid, vid)
                rows.append({
                    'type': 'BY-WEIGHT',
                    'section': cname,
                    'id': '%s / %s' % (iid, vid),
                    'name': '%s — %s' % (iname, label),
                    'current_price': money(vprice),
                    'NEW_PRICE': '',
                    'source': 'menu',
                    'check_this': '',
                })

# ── Modifier options that cost money ──────────────────────────────────────
grp_re = re.compile(r"^        '([a-z0-9_]+)' => \[(.*?)^        \],", re.M | re.S)

for m in grp_re.finditer(mg_block):
    gid, body = m.group(1), m.group(2)
    gname_m = re.search(r"'name' => '((?:[^'\\]|\\.)*)'", body)
    gname = gname_m.group(1).replace("\\'", "'") if gname_m else gid
    is_variant = "'mode' => 'variant'" in body

    for om in re.finditer(r"\['id' => '([a-z0-9_]+)',\s*'name' => (\"[^\"]*\"|'(?:[^'\\]|\\.)*'),\s*'price' => (\d+)\]", body):
        oid, oname, price = om.group(1), om.group(2)[1:-1].replace("\\'", "'"), int(om.group(3))

        assumed = gid in ASSUMED_GROUPS
        derived = gid in DERIVED_GROUPS

        # Free options in an assumed group still matter — the LIST is the guess.
        if price == 0 and not assumed:
            continue

        rows.append({
            'type': 'VARIANT' if is_variant else 'ADD-ON',
            'section': '%s (%s)' % (gname, gid),
            'id': oid,
            'name': oname,
            'current_price': money(price),
            'NEW_PRICE': '',
            'source': 'ASSUMED' if assumed else ('derived' if derived else 'menu'),
            'check_this': ASSUMED_GROUPS.get(gid, DERIVED_GROUPS.get(gid, '')),
        })

# ── Write ─────────────────────────────────────────────────────────────────
with io.open(OUT, 'w', encoding='utf-8-sig', newline='') as f:
    w = csv.writer(f)
    w.writerow(['# Bagel Boyz NJ - Price Sheet'])
    w.writerow(['# Put new prices in the NEW_PRICE column. Leave blank to keep the current one.'])
    w.writerow(['# Use plain dollars: 7.95  (not $7.95, not 795)'])
    w.writerow(['# Rows marked ASSUMED are ones I guessed - please check those first.'])
    w.writerow([])
    w.writerow(['type', 'section', 'id', 'name', 'current_price', 'NEW_PRICE', 'source', 'check_this'])
    for r in rows:
        w.writerow([r['type'], r['section'], r['id'], r['name'],
                    r['current_price'], r['NEW_PRICE'], r['source'], r['check_this']])

items   = sum(1 for r in rows if r['type'] == 'ITEM')
byweight= sum(1 for r in rows if r['type'] == 'BY-WEIGHT')
addons  = sum(1 for r in rows if r['type'] == 'ADD-ON')
variants= sum(1 for r in rows if r['type'] == 'VARIANT')
assumed = sum(1 for r in rows if r['source'] == 'ASSUMED')

print('wrote %s' % OUT)
print('  items            %d' % items)
print('  by-weight prices %d' % byweight)
print('  paid add-ons     %d' % addons)
print('  variants         %d' % variants)
print('  total rows       %d' % len(rows))
print('  ASSUMED (check)  %d' % assumed)
