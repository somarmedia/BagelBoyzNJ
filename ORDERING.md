# Bagel Boyz NJ — Online Ordering System

Pickup ordering for bagelboyznj.com, with an iPad kitchen display and thermal
ticket printing. Runs entirely on the Hostinger shared hosting you already
have — PHP + MySQL, no extra server, no monthly platform fee.

---

## What got built

| Piece | Where | What it does |
|---|---|---|
| **Storefront** | `order.php` | Browse, customize, cart, checkout |
| **Tracking** | `track.php` | Live status page for the customer |
| **Kitchen display** | `kds/` | iPad order board with voice alerts |
| **Menu data** | `data/menu.php` | **Single source of truth for all pricing** |
| **API** | `api/` | Menu, order creation, status, Stripe webhook |
| **Printing** | `print/` | Bridge + WebPRNT + CloudPRNT + Epson, one shared queue |
| **Print bridge** | `backend/src/print-bridge.js` | Runs at the shop, prints to the TSP143IIIW |
| **Database** | `db/schema.sql` | Orders, items, options, print queue, 86 board |

### Order lifecycle

```
Customer places order
   │
   ├─ Pay at pickup ──────────────► status: new  (kitchen sees it immediately)
   │
   └─ Pay by card ─► pending_payment
                          │
                          └─ Stripe webhook confirms ─► status: new
   │
   ▼
new ──► in_progress ──► ready ──► completed
                          │
                          └─ "your order is ready" email fires here
```

A card order is **invisible to the kitchen until Stripe actually confirms the
payment.** The browser saying "it worked" is never trusted.

---

## Try it live, without customers seeing it

Online ordering ships **switched off**. Until you flip it on, every customer
who visits `order.php` sees exactly the DoorDash/Grubhub page they see today —
byte for byte, from `includes/order-legacy.php`. Nothing about their experience
changes.

You get in with a preview key:

```
https://bagelboyznj.com/order.php?preview=YOUR_PREVIEW_KEY
```

That drops a cookie, so from then on you just use the site normally on that
device and see the new system. Do it once on your phone, once on the iPad,
once on whatever Jess uses. To leave: `?preview=off`.

Set the key in `includes/order-config.php`:

```php
'ordering' => [
    'public'      => false,                  // ← the big switch
    'preview_key' => 'something-only-you-know',
],
```

**In preview you get the real system, not a mockup** — real pricing, real
database, real tickets, real emails. That's the point. The differences are
only the safety rails:

- A purple **PREVIEW** banner across the top so you can't mistake it for live
- Orders are stamped `source='preview'`, show a purple **TEST ORDER** badge on
  the iPad, and print with `*** TEST ORDER / DO NOT MAKE THIS ***` at the top
- The API refuses orders from anyone without the cookie, so even someone who
  found the endpoint can't push a real order into your kitchen

**Going live is one line:** `'public' => true`. Flipping it back to `false`
instantly restores the old page — no deploy, no git revert.

### What works with no database yet

If you push before creating the database, preview still shows you the full
menu, the customization sheets and the cart — that's all served from
`data/menu.php`. Only checkout fails, because that's the part that needs to
write an order. Enough to review the menu and pricing; not enough to test the
kitchen flow.

---

## Setup — do these in order

### 1. Create the database

hPanel → **Databases → MySQL Databases** → create a database and a user, give
that user all privileges on it. Write down the four values.

Then hPanel → **phpMyAdmin** → select the database → **Import** → upload
`db/schema.sql` → Go.

You should end up with eight `bb_` tables.

### 2. Create the config file

```bash
cp includes/order-config.example.php includes/order-config.php
```

Fill in the `db` block with what you just created. Then set:

- **`ordering.preview_key`** — your way in before going live. See above.
- **`kds.pin`** — change it off `2021`. This unlocks the iPad.
- **`printing.poll_key`** — a long random string. The print bridge needs this
  and it authorizes fetching kitchen tickets, so make it properly random.
- **`email.store_to`** — where new-order notification emails land.
- **`tax.rate`** — currently `0.0625`. See the tax section below.

`includes/order-config.php` is **gitignored** — it holds secrets, so it will
NOT auto-deploy. Upload it by hand: hPanel → **File Manager** →
`public_html/includes/`. Same rule as `php/smtp-config.php`.

### 3. Deploy

```bash
git add -A
git commit -m "Add online ordering system"
git push origin main
```

Hostinger auto-pulls within seconds. Then upload `includes/order-config.php`
manually as above.

### 4. Check it — still in preview

1. `https://bagelboyznj.com/order.php?preview=YOUR_KEY` — purple banner,
   menu loads, cart works
2. Open it in a private/incognito window with no `?preview=` — you should see
   the **old DoorDash/Grubhub page**. If you don't, stop and check
   `ordering.public` is `false`
3. Place a test order with **Pay at pickup**
4. `https://bagelboyznj.com/kds/` — sign in with your PIN. The order should
   appear with a **TEST ORDER** badge and announce itself out loud
5. Walk it through Start → Ready → Picked Up
6. The tracking link in the confirmation should follow along live
7. If the print bridge is running, a ticket should print with the TEST header

Then flip `'public' => true` when you're happy.

If the menu spins forever, the database connection is wrong. Set
`'debug' => true` in the config temporarily and reload — the API will return
the real error. **Set it back to `false` before you're done.**

---

## The iPad

1. Open `https://bagelboyznj.com/kds/` in Safari
2. Share → **Add to Home Screen** — it then runs full-screen like a real app
3. Sign in with the PIN, pick the location
4. Tap **Start Shift** to enable sound *(iOS blocks audio until you tap
   something — this is that tap, and it's required once per session)*
5. Settings → **Auto-Lock → Never**, and keep it plugged in

### The alert

When a new order lands, the iPad plays a rising chime and speaks
*"You have a new order"* — repeating every 6 seconds until someone taps
**GOT IT**. Adjust in config:

```php
'kds' => [
    'alert_repeat_seconds' => 6,                      // how often it repeats
    'alert_voice_text'     => 'You have a new order', // what it says
],
```

### What staff can do from the iPad

- **Start → Ready → Picked Up** on each order
- **86 board** (🚫 icon) — tap any item to pull it off the online menu instantly
- **Settings** (⚙️) — change the quoted wait time, pause orders during a rush,
  or stop online ordering entirely
- **Reprint** a ticket from the order detail

---

## Printing — Star TSP143IIIW (wifi)

### Two facts that drive everything here

1. **The TSP100III can't pull jobs from the web.** CloudPRNT — where the
   printer fetches its own tickets — starts at the TSP100**IV**.
2. **The iPad can't push to it from a browser.** The site is HTTPS, the
   printer is plain HTTP on a local IP, and browsers block that.
   **Switching to Chrome does not help** — iOS forces every browser onto
   Apple's WebKit engine, so Chrome, Firefox and Edge on iPad are all Safari
   underneath with identical rules.

So there are three ways to print. Use the first.

---

### ✅ Recommended: the local print bridge

A ~200-line Node script on any always-on machine on the shop wifi. It calls
out to bagelboyznj.com over normal HTTPS, gets the ticket, and writes it
straight to the printer on TCP port 9100.

```
   internet                     shop wifi
┌────────────┐   HTTPS GET    ┌────────┐   TCP :9100   ┌─────────┐
│ bagelboyz  │ ◄───────────── │ bridge │ ────────────► │ TSP143  │
│   .com     │ ──── job ────► │        │               │  IIIW   │
└────────────┘                └────────┘               └─────────┘
```

**Why this one:** no browser is involved, so the mixed-content problem simply
doesn't exist. Tickets print whether or not anyone is looking at the iPad, and
the iPad becomes purely a display. It's outbound-only — no port forwarding, no
static WAN IP, nothing to open on your router.

**What it runs on:** a Raspberry Pi (~$15), an old laptop, or the back-office
PC. Anything that stays on and speaks wifi. It idles at a few MB of RAM.

**Setup:**

1. Get the printer's IP — hold **FEED** while powering it on and it prints a
   self-test page showing it
2. **Give the printer a static IP** in your router. If it changes, printing
   stops. This is the single most common cause of "it just stopped working"
3. On the shop machine:

```bash
git clone <your repo>          # or copy the backend/ folder over
cd backend
npm install                    # the bridge itself needs no packages
cp .env.example .env
```

4. In `.env`, set `BB_PRINT_KEY` (must match `printing.poll_key` in
   `includes/order-config.php`) and `BB_PRINTER_IP`
5. Test in the foreground first — it prints its config and probes the printer:

```bash
node src/print-bridge.js
```

6. Once it's printing, run it for real:

```bash
pm2 start ecosystem.config.js --only bb-print-bridge
pm2 save && pm2 startup        # survive a reboot
```

Watch it with `pm2 logs bb-print-bridge`.

If the printer is off or the wifi drops, it backs off and retries, logs the
problem once rather than spamming, and marks the job failed so staff can
reprint from the iPad.

---

### Alternative: WebPRNT from the iPad

Already built. The iPad pushes to the printer itself — but only from inside
**Star WebPRNT Browser**, Star's free iOS app, which isn't subject to the
mixed-content block. Load the KDS URL in that app and set the printer IP in
KDS → Settings.

Works fine, but it depends on that app staying available and on the iPad being
awake and on the right screen. The bridge has neither constraint.

---

### Always available: AirPrint

If no printer IP is set, the print button on any order opens the iOS print
sheet. Works with any printer, needs a tap per ticket. This is the backstop —
it's there even if everything else is misconfigured.

---

### If you later buy a TSP100IV

CloudPRNT is built and waiting. Point the printer's CloudPRNT URL at:

```
https://bagelboyznj.com/print/cloudprnt.php?loc=holmdel&key=YOUR_POLL_KEY
```

Then you can retire the bridge — the printer does it all itself.

### Epson

`print/epos.php` (Server Direct Print) is built too, same URL pattern, in case
a TM-m30 ever replaces the Star. All four drivers share one job queue, so
switching is a config flag, not a rewrite.

---

## Connecting Stripe

The checkout is built and waiting. Until you fill in keys, the card option is
hidden and everyone pays at pickup — the system is fully usable in that state.

When you're ready:

1. dashboard.stripe.com → **Developers → API keys** → copy the publishable
   (`pk_live_…`) and secret (`sk_live_…`) keys
2. **Developers → Webhooks → Add endpoint**
   - URL: `https://bagelboyznj.com/api/stripe-webhook.php`
   - Events: `payment_intent.succeeded`, `payment_intent.payment_failed`,
     `charge.refunded`
   - Copy the signing secret (`whsec_…`)
3. Put all three in `includes/order-config.php` and set `'enabled' => true`

Test with `pk_test_`/`sk_test_` keys and card `4242 4242 4242 4242` first.

The webhook is what marks an order paid and releases it to the kitchen. If it
isn't configured, card orders will sit at `pending_payment` forever and never
print. **Set up the webhook, not just the keys.**

---

## Editing the menu

Everything lives in **`data/menu.php`**. It is the only place prices exist —
the server re-prices every order from this file and ignores whatever the
browser sends, so a customer can't edit their own total.

**All prices are in integer cents.** `$1.50` is `150`. Never use decimals.

Change a price:

```php
['id' => 'bf_eggs_cheese', 'name' => 'Eggs & Cheese', 'price' => 579, ...]
//                                                              ^^^ $5.79
```

Add an item to a category:

```php
['id' => 'bf_new_thing', 'name' => 'The New Thing', 'price' => 899,
 'desc' => 'What is on it',
 'groups' => ['bread_choice', 'bagel_type', 'cheese_choice', 'toasted', 'add_ons', 'condiments']],
```

`groups` are the customization questions. They're defined at the top of the
same file — reuse the existing ones.

> Note: `menu.php` (the public menu page) is still hand-written HTML and is
> **separate** from `data/menu.php`. Right now a price change needs making in
> both. Worth unifying later so the menu page renders from the data file.

### The price sheet

**`data/pricing-template.csv`** is a full, editable export of every price in
the system — open it in Excel or Google Sheets.

| Column | What it's for |
|---|---|
| `type` | ITEM, BY-WEIGHT, ADD-ON or VARIANT |
| `current_price` | What the system charges today |
| `NEW_PRICE` | **Put your price here.** Leave blank to keep the current one |
| `source` | Where the price came from — see below |
| `check_this` | Why a row needs attention |

Fill in `NEW_PRICE` in plain dollars (`7.95`, not `$7.95`), send it back, and
I'll rebuild `data/menu.php` from it.

**Sort by the `source` column and start with the ASSUMED rows** — those are
prices or option lists I had to guess because the printed menu doesn't state
them:

- **Soda, juice, bottled water** — the menu just says "Varies". I put $2.50 /
  $3.00 / $2.00 as placeholders. These are almost certainly wrong.
- **Cheese varieties** — not listed anywhere on the menu
- **Cream cheese flavors** — not listed anywhere on the menu
- **Condiments and coffee prep** — not listed anywhere on the menu

Everything marked `menu` came straight off the current menu page.
Everything marked `derived` was calculated from a note on it (e.g. "roll or
wrap add $0.75", "egg whites add $0.99").

To regenerate the sheet after a menu change:

```bash
python tools/export-pricing.py
```

---

## Sales tax — read this before going live

Configured at **6.25%** as requested, with the **Bagels** category marked
tax-exempt.

> ⚠️ **The NJ state sales tax rate is 6.625%, not 6.25%.** If 6.25% was a
> typo, fix `tax.rate` in `includes/order-config.php`. At 6.25% you
> under-collect by about **$0.38 per $100** of taxable sales and the shop
> covers the difference at filing time. The only NJ rate below 6.625% is the
> 3.3125% Urban Enterprise Zone rate, and Hazlet is not a UEZ.

The exemption reflects the general NJ rule that unheated bakery items sold
as-is aren't taxable while prepared food is.

**I'm not your accountant — have them confirm both the rate and the
exemption.**

To tax everything instead (overcharges slightly on plain bagels, but you're
never short):

```php
'tax' => [ 'rate' => 0.06625, 'honor_exempt' => false ],
```

---

## Day-to-day

**Slammed?** iPad → Settings → Pause for a rush → 15/30/60 min. Auto-resumes.

**Out of something?** iPad → 🚫 → tap the item. It goes off the online menu
instantly and anything already in a customer's cart gets rejected at checkout
with a clear message.

**Longer wait than usual?** iPad → Settings → Quoted wait time.

**Closing early?** iPad → Settings → toggle off "Taking online orders."

Hours are `6:00–15:00` daily in the config, and online orders stop 20 minutes
before close so nothing lands while you're cleaning up. Holidays go in
`closed_dates`.

---

## Troubleshooting

**Menu won't load** — database connection. Set `'debug' => true`, reload
`api/menu.php?location=holmdel` directly, read the error, set it back.

**Orders not reaching the iPad** — check the KDS is signed in to the right
location. Check the connection dot in the top right: green = fine,
amber/red = the iPad lost wifi.

**No sound** — someone reloaded the page and skipped **Start Shift**. iOS
requires that tap before it will allow audio. Settings → Test alert to verify.

**Card orders never print** — the Stripe webhook isn't configured or is
failing. Check Stripe Dashboard → Webhooks for delivery errors.

**Nothing prints** — check the printer IP in KDS Settings, and that the iPad
and printer are on the same wifi. Use the print button on an order to force
the AirPrint fallback as a workaround.

**Emails not sending** — needs `php/smtp-config.php` uploaded, same file the
catering form uses. Order emails fail silently on purpose: a mail problem
must never lose an order.

---

## Security notes

- Prices are **always** recomputed server-side from `data/menu.php`
- `includes/`, `data/` and `db/` each carry an `.htaccess` denying web access
- The tracking page needs a 32-char token, so order codes can't be enumerated
- KDS sessions are HttpOnly + SameSite cookies; PIN attempts are rate-limited
  to 6 per 15 min per IP
- Order placement is rate-limited to 12/hour per IP
- Stripe webhooks are signature-verified with a replay window
- reCAPTCHA Enterprise runs on checkout, reusing your existing site setup

---

## Not built yet

Deliberately out of scope for this pass — say the word on any of them:

- **SMS notifications** (Twilio) — email + tracking page only right now
- **Delivery** — pickup only, as agreed
- **Order history / customer accounts** — every order is a guest order
- **Owner reporting dashboard** — sales data is all in `bb_orders`, but there's
  no UI over it yet
- **Unified menu rendering** — `menu.php` and `data/menu.php` are still separate
