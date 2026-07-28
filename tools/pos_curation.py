# -*- coding: utf-8 -*-
"""
Curation layer for the Bagel Boyz POS → online-menu build.

The register export (menuprg/BAGEL BOYZ.prg) is the source of truth for names
and prices, but its text is written for a cashier: ALL CAPS, decorated with
* / ** / ^ markers, and heavy with abbreviations (PEC, POBO, TB/EC). This
module turns that into customer-facing language and maps the POS condiment
groups onto clean, reusable modifier groups.

Everything price-related is pulled from the POS at build time; this file only
governs naming, ordering, and which modifier groups apply.
"""
import re

# =====================================================================
# NAME CLEANUP
# =====================================================================

def clean_option(name):
    """Strip cashier decoration from a condiment/option name and Title-case it."""
    n = name.strip()
    n = re.sub(r'[\*\^]+', ' ', n)          # remove * and ^ decoration
    n = re.sub(r'\s+', ' ', n).strip()
    n = n.strip('-').strip()
    # Common POS spelling fixes.
    fixes = {
        'SCAMBLED': 'Scrambled', 'SCAMBLED SOFT': 'Scrambled Soft',
        'CHIPOLTE MAYO': 'Chipotle Mayo', 'GORMET C.C.-LITE': 'Gourmet Cream Cheese (Light)',
        'C.C.': 'Cream Cheese', 'GOURMET C.C.': 'Gourmet Cream Cheese',
        'W.W. WRAP': 'Whole Wheat Wrap', 'W.W. BREAD': 'Whole Wheat Bread',
        'S,P,K': 'Salt, Pepper & Ketchup', 'S,P': 'Salt & Pepper',
        'VINEGAR & OIL': 'Oil & Vinegar', "JALAPENO'S": 'Jalapeños',
        'PEPPERJACK': 'Pepper Jack', 'MG': 'Multigrain',
    }
    up = n.upper()
    if up in fixes:
        return fixes[up]
    # Title-case but keep short all-caps tokens sane.
    words = []
    for w in n.split():
        if w.upper() in ('C.C.', 'SPK', 'BLT', 'W.W.'):
            words.append(w.upper())
        elif '.' in w:
            words.append(w)  # leave abbreviations like 1/2 lb alone-ish
        else:
            words.append(w.capitalize())
    out = ' '.join(words)
    # Targeted word fixes after title-casing.
    out = (out.replace('C.c.', 'Cream Cheese').replace('Cc', 'Cream Cheese')
              .replace('Ww', 'Whole Wheat').replace('Spk', 'SPK'))
    return out


# Bagel-type abbreviations (POS condiment group 1 / 18).
BAGEL_TYPES = {
    'PL': 'Plain', 'EV': 'Everything', 'E': 'Egg', 'EEV': 'Egg Everything',
    'CR': 'Cinnamon Raisin', 'SES': 'Sesame', 'S': 'Salt', 'P': 'Poppy',
    'O': 'Onion', 'MG': 'Multigrain', 'MGEV': 'Multigrain Everything',
    'PN': 'Pumpernickel', 'PNEV': 'Pumpernickel Everything', 'G': 'Garlic',
    'RAIN': 'Rainbow', 'BB': 'Blueberry', 'GF': 'Gluten Free', 'FT': 'French Toast',
    'WHOLE WHEAT': 'Whole Wheat',
}


def clean_bagel_option(name):
    raw = re.sub(r'[\*\^]+', ' ', name).strip().upper()
    raw = re.sub(r'\s+', ' ', raw)
    if raw in BAGEL_TYPES:
        return BAGEL_TYPES[raw]
    return clean_option(name)


# =====================================================================
# ITEM NAME EXPANSION  (breakfast codes → readable names)
# =====================================================================
# Meat legend used across the breakfast board:
#   P = Taylor Ham / Pork Roll, B = Bacon, S = Sausage, H = Ham,
#   TB = Turkey Bacon, T = Taylor Ham (menu also lists a plain "Pork Roll")
# Suffix: E = Egg, C = Cheese, OBO = "on a bagel" w/ hash brown.
ITEM_NAMES = {
    'PE': 'Taylor Ham & Egg', 'PEC': 'Taylor Ham, Egg & Cheese', 'PC': 'Taylor Ham & Cheese',
    'BE': 'Bacon & Egg', 'BEC': 'Bacon, Egg & Cheese', 'BC': 'Bacon & Cheese',
    'SE': 'Sausage & Egg', 'SEC': 'Sausage, Egg & Cheese', 'SC': 'Sausage & Cheese',
    'HE': 'Ham & Egg', 'HEC': 'Ham, Egg & Cheese', 'HC': 'Ham & Cheese',
    'TE': 'Taylor Ham & Egg', 'TEC': 'Taylor Ham, Egg & Cheese', 'TC': 'Taylor Ham & Cheese',
    'T': 'Taylor Ham', 'TB/E': 'Turkey Bacon & Egg', 'TB/EC': 'Turkey Bacon, Egg & Cheese',
    'TB/C': 'Turkey Bacon & Cheese',
    'POBO': 'Pork Roll, Egg & Cheese on a Roll (w/ Hash Brown)',
    'SOBO': 'Sausage, Egg & Cheese on a Roll (w/ Hash Brown)',
    'BOBO': 'Bacon, Egg & Cheese on a Roll (w/ Hash Brown)',
    'OBO': 'Egg & Cheese on a Roll (w/ Hash Brown)',
    'BLT': 'BLT', 'GRILL CHEESE': 'Grilled Cheese',
    'BIG ROB G BELLY BUSTER': 'Big Rob "G" Belly Buster',
    'RJ SPECIAL': 'RJ Special', 'STEAK EGG CHEESE': 'Steak, Egg & Cheese',
    'HOT HONEY CRUNCH PEC': 'Hot Honey Crunch (Taylor Ham, Egg & Cheese)',
    'JERSEY NACHO BEC': 'Jersey Nacho (Bacon, Egg & Cheese)',
    'SWEET HEAT SEC': 'Sweet Heat (Sausage, Egg & Cheese)',
    'MIKE\'S BLT/WHITE BREAD': "Mike's BLT on White Bread",
    'HEALTHY TURKEY SANDWICH': 'Healthy Turkey', 'HEALTHY CHICKEN SANDWICH': 'Healthy Chicken',
    'HEALTHY HEALTHY SANDWICH': 'Healthy Healthy',
    'AVOCADO SANDWICH': 'Avocado Toast', 'GLUTEN FREE BAGEL': 'Gluten Free Bagel',
}


def titleize(name):
    """Human-friendly Title Case for a full item name, preserving key tokens."""
    n = name.strip()
    up = n.upper()
    if up in ITEM_NAMES:
        return ITEM_NAMES[up]
    n = re.sub(r'\s+', ' ', n)
    # Expand common abbreviations found inside item names.
    n = re.sub(r'C\.C\.', 'Cream Cheese', n, flags=re.I)
    n = re.sub(r'O\.G\.', 'Oven Gold', n, flags=re.I)
    n = re.sub(r'\bER\b', 'EverRoast', n)
    n = re.sub(r'\bDOM\.\b', 'Domestic', n, flags=re.I)
    n = re.sub(r'\bGR\.\b', 'Grilled', n, flags=re.I)
    n = re.sub(r'\bB\.H\.\b', "Boar's Head", n, flags=re.I)
    n = re.sub(r'\bGOUR\b', 'Gourmet', n, flags=re.I)
    n = re.sub(r'\bBAC\b', 'Bacon', n, flags=re.I)
    n = re.sub(r'\bW/\b', 'with ', n)
    n = n.replace('W/', 'with ')

    words, out = n.split(' '), []
    keep_upper = {'BLT', 'RJ', 'SPK', 'OBO', 'POBO', 'SOBO', 'BOBO', 'BBQ'}
    for w in words:
        bare = re.sub(r'[^A-Za-z]', '', w)
        if bare.upper() in keep_upper:
            out.append(w.upper())
        elif w.isupper() and len(bare) > 1:
            out.append(w.capitalize())
        else:
            out.append(w)
    res = ' '.join(out)
    res = res[:1].upper() + res[1:] if res else res
    return res.replace('  ', ' ').strip()
