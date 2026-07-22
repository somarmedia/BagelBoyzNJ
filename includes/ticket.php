<?php
/**
 * Bagel Boyz NJ — kitchen ticket rendering.
 *
 * One ticket is built once as a neutral list of "blocks", then rendered into
 * whichever dialect the printer on the counter happens to speak:
 *
 *   bb_ticket_star()  → Star Markup       (text/vnd.star.markup)  — CloudPRNT
 *   bb_ticket_epos()  → ePOS-Print XML    (text/xml)              — Epson SDP
 *   bb_ticket_text()  → plain text                                — email / debug
 *   bb_ticket_html()  → styled HTML                               — iPad AirPrint
 *
 * Adding a third printer brand later means adding one renderer, not
 * rewriting the ticket.
 */

require_once __DIR__ . '/order-lib.php';

/**
 * Build the neutral block list for an order.
 * Block kinds: rule | blank | line | big | center | kv | item | opt | note
 */
function bb_ticket_blocks(array $order) {
    $cfg  = bb_config();
    $loc  = $cfg['locations'][$order['location_id']] ?? ['name' => $order['location_id']];
    $b    = [];

    $tz      = new DateTimeZone(bb_config('timezone', 'America/New_York'));
    $created = new DateTime($order['created_at'], $tz);

    /* ---- header ---- */
    // A preview order must be unmistakable on paper too, or someone will
    // walk a test sandwich to the front counter.
    if (($order['source'] ?? 'web') === 'preview') {
        $b[] = ['rule'];
        $b[] = ['big', '*** TEST ORDER ***'];
        $b[] = ['center', 'DO NOT MAKE THIS'];
        $b[] = ['rule'];
        $b[] = ['blank'];
    }

    $b[] = ['center', 'BAGEL BOYZ NJ'];
    $b[] = ['center', $loc['name']];
    $b[] = ['blank'];
    $b[] = ['big', $order['order_code']];
    $b[] = ['blank'];

    /* ---- when ---- */
    if ($order['pickup_type'] === 'scheduled' && !empty($order['pickup_at'])) {
        $pu = new DateTime($order['pickup_at'], $tz);
        $sameDay = $pu->format('Y-m-d') === $created->format('Y-m-d');
        $b[] = ['big', 'PICKUP ' . $pu->format($sameDay ? 'g:i A' : 'D g:i A')];
    } else {
        $b[] = ['big', 'ASAP'];
        if (!empty($order['pickup_at'])) {
            $pu = new DateTime($order['pickup_at'], $tz);
            $b[] = ['center', 'quoted ' . $pu->format('g:i A')];
        }
    }
    $b[] = ['blank'];
    $b[] = ['rule'];

    /* ---- who ---- */
    $b[] = ['line', mb_strtoupper($order['customer_name'], 'UTF-8')];
    $b[] = ['line', $order['customer_phone']];
    $b[] = ['line', 'Ordered ' . $created->format('g:i A')];
    $b[] = ['rule'];

    /* ---- items ---- */
    foreach ($order['items'] ?? [] as $it) {
        $b[] = ['item', (int) $it['qty'] . 'x ' . $it['name'], bb_money($it['line_cents'])];

        foreach ($it['options'] ?? [] as $o) {
            // A zero-price condiment still matters to the cook; print all of them.
            $label = $o['option_name'];
            if ((int) $o['price_cents'] > 0) {
                $label .= ' (+' . bb_money($o['price_cents']) . ')';
            }
            $b[] = ['opt', $label];
        }

        if (!empty($it['notes'])) {
            $b[] = ['note', '** ' . mb_strtoupper($it['notes'], 'UTF-8')];
        }
        $b[] = ['blank'];
    }

    /* ---- order-level note ---- */
    if (!empty($order['order_notes'])) {
        $b[] = ['rule'];
        $b[] = ['line', 'ORDER NOTE:'];
        $b[] = ['note', mb_strtoupper($order['order_notes'], 'UTF-8')];
    }

    /* ---- money ---- */
    $b[] = ['rule'];
    $b[] = ['kv', 'Subtotal', bb_money($order['subtotal_cents'])];
    if ((int) $order['tax_cents'] > 0) $b[] = ['kv', 'Tax', bb_money($order['tax_cents'])];
    if ((int) $order['tip_cents'] > 0) $b[] = ['kv', 'Tip', bb_money($order['tip_cents'])];
    $b[] = ['kv', 'TOTAL', bb_money($order['total_cents'])];
    $b[] = ['blank'];

    /* ---- payment: the single most important line for the counter ---- */
    if ($order['payment_status'] === 'paid') {
        $b[] = ['big', '*** PAID ONLINE ***'];
    } else {
        $b[] = ['big', '!! COLLECT ' . bb_money($order['total_cents']) . ' !!'];
    }

    $b[] = ['blank'];
    $b[] = ['center', 'bagelboyznj.com'];

    return $b;
}

/* =====================================================================
   PLAIN TEXT
   ===================================================================== */
function bb_ticket_text(array $order, $width = null) {
    $width  = $width ?: (int) bb_config('printing.chars_per_line', 42);
    $out    = [];

    foreach (bb_ticket_blocks($order) as $blk) {
        switch ($blk[0]) {
            case 'rule':   $out[] = str_repeat('-', $width); break;
            case 'blank':  $out[] = ''; break;
            case 'center': $out[] = str_pad('', max(0, intdiv($width - mb_strlen($blk[1]), 2))) . $blk[1]; break;
            case 'big':    $out[] = str_pad('', max(0, intdiv($width - mb_strlen($blk[1]), 2))) . $blk[1]; break;
            case 'line':   $out[] = $blk[1]; break;
            case 'opt':    $out[] = '   - ' . $blk[1]; break;
            case 'note':   $out[] = '   ' . $blk[1]; break;
            case 'kv':
            case 'item':
                $left  = $blk[1];
                $right = $blk[2] ?? '';
                $gap   = $width - mb_strlen($left) - mb_strlen($right);
                $out[] = $gap > 0 ? $left . str_repeat(' ', $gap) . $right : $left . ' ' . $right;
                break;
        }
    }
    return implode("\n", $out) . "\n";
}

/* =====================================================================
   STAR MARKUP  (Star CloudPRNT — text/vnd.star.markup)
   Docs: star-m.jp CloudPRNT "Star Document Markup"
   ===================================================================== */
function bb_ticket_star(array $order) {
    $width = (int) bb_config('printing.chars_per_line', 42);
    $s     = "[align: centre]\n";

    foreach (bb_ticket_blocks($order) as $blk) {
        switch ($blk[0]) {
            case 'rule':
                $s .= "[align: left]" . str_repeat('-', $width) . "\n";
                break;
            case 'blank':
                $s .= "\n";
                break;
            case 'center':
                $s .= "[align: centre]" . bb_star_esc($blk[1]) . "\n";
                break;
            case 'big':
                // Double-width + double-height, then reset back to normal.
                $s .= "[align: centre][magnify: width 2; height 2]" . bb_star_esc($blk[1]) . "[magnify: width 1; height 1]\n";
                break;
            case 'line':
                $s .= "[align: left]" . bb_star_esc($blk[1]) . "\n";
                break;
            case 'item':
                $s .= "[align: left][bold]" . bb_star_esc(bb_ticket_row($blk[1], $blk[2] ?? '', $width)) . "[bold: off]\n";
                break;
            case 'opt':
                $s .= "[align: left]   - " . bb_star_esc($blk[1]) . "\n";
                break;
            case 'note':
                $s .= "[align: left][bold]   " . bb_star_esc($blk[1]) . "[bold: off]\n";
                break;
            case 'kv':
                $s .= "[align: left]" . bb_star_esc(bb_ticket_row($blk[1], $blk[2] ?? '', $width)) . "\n";
                break;
        }
    }

    // Feed clear of the tear bar, cut, then kick the drawer if one is wired.
    $s .= "\n\n[cut: feed; partial]\n";
    return $s;
}

/** Star Markup treats [ as a command opener — escape literal brackets. */
function bb_star_esc($text) {
    return str_replace(['[', ']'], ['[[', ']]'], $text);
}

function bb_ticket_row($left, $right, $width) {
    $gap = $width - mb_strlen($left) - mb_strlen($right);
    return $gap > 0 ? $left . str_repeat(' ', $gap) . $right : $left . ' ' . $right;
}

/* =====================================================================
   ePOS-Print XML  (Epson Server Direct Print)
   Docs: Epson ePOS-Print XML User's Manual
   ===================================================================== */
function bb_ticket_epos(array $order) {
    $x = '<epos-print xmlns="http://www.epson-pos.com/schemas/2011/03/epos-print">';

    foreach (bb_ticket_blocks($order) as $blk) {
        switch ($blk[0]) {
            case 'rule':
                $x .= '<text align="left"/><text>' . str_repeat('-', (int) bb_config('printing.chars_per_line', 42)) . '&#10;</text>';
                break;
            case 'blank':
                $x .= '<feed line="1"/>';
                break;
            case 'center':
                $x .= '<text align="center"/><text>' . bb_xml($blk[1]) . '&#10;</text>';
                break;
            case 'big':
                $x .= '<text align="center"/><text width="2" height="2"/>'
                    . '<text>' . bb_xml($blk[1]) . '&#10;</text>'
                    . '<text width="1" height="1"/>';
                break;
            case 'line':
                $x .= '<text align="left"/><text>' . bb_xml($blk[1]) . '&#10;</text>';
                break;
            case 'item':
                $x .= '<text align="left"/><text em="true"/>'
                    . '<text>' . bb_xml(bb_ticket_row($blk[1], $blk[2] ?? '', (int) bb_config('printing.chars_per_line', 42))) . '&#10;</text>'
                    . '<text em="false"/>';
                break;
            case 'opt':
                $x .= '<text align="left"/><text>   - ' . bb_xml($blk[1]) . '&#10;</text>';
                break;
            case 'note':
                $x .= '<text align="left"/><text em="true"/><text>   ' . bb_xml($blk[1]) . '&#10;</text><text em="false"/>';
                break;
            case 'kv':
                $x .= '<text align="left"/><text>' . bb_xml(bb_ticket_row($blk[1], $blk[2] ?? '', (int) bb_config('printing.chars_per_line', 42))) . '&#10;</text>';
                break;
        }
    }

    $x .= '<feed line="3"/><cut type="feed"/>';
    $x .= '</epos-print>';
    return $x;
}

function bb_xml($text) {
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/* =====================================================================
   StarWebPRNT XML  (Star TSP100III LAN / WiFi)
   ---------------------------------------------------------------------
   The TSP100III has no CloudPRNT firmware — it can't pull jobs from a
   server. Instead the iPad PUSHES this XML to the printer on the local
   network:  POST http://<printer-ip>/StarWebPRNT/SendMessage
   See js/kds.js → bbPrintWebPRNT() and ORDERING.md for the wiring.
   ===================================================================== */
function bb_ticket_webprnt(array $order) {
    $width = (int) bb_config('printing.chars_per_line', 42);
    $x  = '<?xml version="1.0" encoding="utf-8"?>' . "\n<root>\n";

    // Start from a known state — the printer may retain settings from the
    // last job it ran.
    $x .= '<initialization/>';

    $line = function ($text) { return bb_xml($text) . '&#10;'; };

    foreach (bb_ticket_blocks($order) as $blk) {
        switch ($blk[0]) {
            case 'rule':
                $x .= '<alignment position="left"/><text>' . str_repeat('-', $width) . '&#10;</text>';
                break;
            case 'blank':
                $x .= '<text>&#10;</text>';
                break;
            case 'center':
                $x .= '<alignment position="center"/><text>' . $line($blk[1]) . '</text>';
                break;
            case 'big':
                $x .= '<alignment position="center"/><text width="2" height="2">' . $line($blk[1]) . '</text>'
                    . '<text width="1" height="1"/>';
                break;
            case 'line':
                $x .= '<alignment position="left"/><text>' . $line($blk[1]) . '</text>';
                break;
            case 'item':
                $x .= '<alignment position="left"/><text emphasis="true">'
                    . $line(bb_ticket_row($blk[1], $blk[2] ?? '', $width))
                    . '</text><text emphasis="false"/>';
                break;
            case 'opt':
                $x .= '<alignment position="left"/><text>' . $line('   - ' . $blk[1]) . '</text>';
                break;
            case 'note':
                $x .= '<alignment position="left"/><text emphasis="true">' . $line('   ' . $blk[1]) . '</text><text emphasis="false"/>';
                break;
            case 'kv':
                $x .= '<alignment position="left"/><text>' . $line(bb_ticket_row($blk[1], $blk[2] ?? '', $width)) . '</text>';
                break;
        }
    }

    $x .= '<text>&#10;&#10;</text>';
    $x .= '<cutpaper feed="true" type="partial"/>';
    $x .= "\n</root>";
    return $x;
}

/* =====================================================================
   STAR LINE MODE  (raw bytes over TCP port 9100)
   ---------------------------------------------------------------------
   This is what the local print bridge sends. Unlike WebPRNT it needs no
   browser, no HTTPS/HTTP mixed-content workaround and no iPad — a small
   always-on machine on the shop network pulls the job from this server
   and writes these bytes straight at the printer.
     See backend/src/print-bridge.js and print/bridge.php.

   Star Line Mode command reference:
     ESC @          1B 40         initialize
     ESC GS a n     1B 1D 61 n    align   (0=left 1=centre 2=right)
     ESC E / ESC F  1B 45 / 1B 46 emphasis on / off
     ESC i n1 n2    1B 69 n1 n2   expand  (n1=height 0-5, n2=width 0-5)
     ESC d n        1B 64 n       cut     (3 = partial, with feed)
   ===================================================================== */
function bb_ticket_starline(array $order) {
    $width = (int) bb_config('printing.chars_per_line', 42);

    $ESC = "\x1B";
    $out = $ESC . '@';                       // initialize

    $align = function ($n) use ($ESC) { return $ESC . "\x1D" . 'a' . chr($n); };
    $big   = $ESC . 'i' . chr(1) . chr(1);   // double height + width
    $norm  = $ESC . 'i' . chr(0) . chr(0);
    $boldOn  = $ESC . 'E';
    $boldOff = $ESC . 'F';

    // Thermal printers are single-byte; transliterate anything outside
    // ASCII (é, ñ, smart quotes) rather than emitting mojibake.
    $ascii = function ($text) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return $t === false ? preg_replace('/[^\x20-\x7E]/', '', $text) : $t;
    };

    foreach (bb_ticket_blocks($order) as $blk) {
        switch ($blk[0]) {
            case 'rule':
                $out .= $align(0) . str_repeat('-', $width) . "\n";
                break;
            case 'blank':
                $out .= "\n";
                break;
            case 'center':
                $out .= $align(1) . $ascii($blk[1]) . "\n";
                break;
            case 'big':
                $out .= $align(1) . $big . $ascii($blk[1]) . $norm . "\n";
                break;
            case 'line':
                $out .= $align(0) . $ascii($blk[1]) . "\n";
                break;
            case 'item':
                $out .= $align(0) . $boldOn
                      . $ascii(bb_ticket_row($blk[1], $blk[2] ?? '', $width))
                      . $boldOff . "\n";
                break;
            case 'opt':
                $out .= $align(0) . '   - ' . $ascii($blk[1]) . "\n";
                break;
            case 'note':
                $out .= $align(0) . $boldOn . '   ' . $ascii($blk[1]) . $boldOff . "\n";
                break;
            case 'kv':
                $out .= $align(0) . $ascii(bb_ticket_row($blk[1], $blk[2] ?? '', $width)) . "\n";
                break;
        }
    }

    // Feed the ticket clear of the tear bar before cutting.
    $out .= "\n\n\n" . $ESC . 'd' . chr(3);
    return $out;
}

/* =====================================================================
   HTML  (iPad AirPrint fallback — works with any printer, zero setup)
   ===================================================================== */
function bb_ticket_html(array $order) {
    $h = '<div class="bb-ticket">';
    foreach (bb_ticket_blocks($order) as $blk) {
        $e = function ($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); };
        switch ($blk[0]) {
            case 'rule':   $h .= '<hr>'; break;
            case 'blank':  $h .= '<div class="sp"></div>'; break;
            case 'center': $h .= '<div class="c">' . $e($blk[1]) . '</div>'; break;
            case 'big':    $h .= '<div class="big">' . $e($blk[1]) . '</div>'; break;
            case 'line':   $h .= '<div>' . $e($blk[1]) . '</div>'; break;
            case 'opt':    $h .= '<div class="opt">&ndash; ' . $e($blk[1]) . '</div>'; break;
            case 'note':   $h .= '<div class="note">' . $e($blk[1]) . '</div>'; break;
            case 'item':   $h .= '<div class="row item"><span>' . $e($blk[1]) . '</span><span>' . $e($blk[2] ?? '') . '</span></div>'; break;
            case 'kv':     $h .= '<div class="row"><span>' . $e($blk[1]) . '</span><span>' . $e($blk[2] ?? '') . '</span></div>'; break;
        }
    }
    $h .= '</div>';
    return $h;
}

/* =====================================================================
   QUEUEING
   ===================================================================== */

/** Drop a print job on the queue for whichever printer polls this location. */
function bb_queue_print(PDO $pdo, array $order) {
    if (!bb_config('printing.enabled', true)) return false;

    try {
        $copies = max(1, (int) bb_config('printing.copies', 1));
        $st = $pdo->prepare(
            'INSERT INTO bb_print_jobs (order_id, location_id, copies, status, job_token)
             VALUES (?,?,?,?,?)'
        );
        $st->execute([$order['id'], $order['location_id'], $copies, 'queued', bb_token()]);

        $pdo->prepare('UPDATE bb_orders SET print_status = ? WHERE id = ?')
            ->execute(['queued', $order['id']]);

        return true;
    } catch (Exception $e) {
        error_log('BB PRINT: could not queue job for order ' . $order['id'] . ' — ' . $e->getMessage());
        return false;
    }
}
