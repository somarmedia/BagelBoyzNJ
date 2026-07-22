<?php
/**
 * Bagel Boyz NJ — order email.
 *
 * Reuses the PHPMailer + php/smtp-config.php setup the catering and careers
 * forms already run on, so there's one set of mail credentials to maintain.
 *
 * Every function here is best-effort: mail failing must NEVER fail an order.
 * We log and move on — the customer still has their tracking page and the
 * kitchen still has the ticket.
 */

require_once __DIR__ . '/order-lib.php';
require_once __DIR__ . '/ticket.php';

function bb_smtp_config() {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $path = __DIR__ . '/../php/smtp-config.php';
    if (!file_exists($path)) {
        error_log('BB MAIL: php/smtp-config.php missing — order emails disabled.');
        return $cfg = false;
    }

    // Cached in the static above, so the file executes at most once per
    // request no matter how many callers ask for it.
    $loaded = require $path;
    return $cfg = (is_array($loaded) ? $loaded : false);
}

function bb_send_mail($to, $subject, $bodyText, $bodyHtml = null, $replyTo = null) {
    if (!bb_config('email.enabled', true)) return false;

    $smtp = bb_smtp_config();
    if (!$smtp || empty($to)) return false;

    require_once __DIR__ . '/../php/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../php/PHPMailer/SMTP.php';
    require_once __DIR__ . '/../php/PHPMailer/Exception.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtp['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['username'];
        $mail->Password   = $smtp['password'];
        $mail->SMTPSecure = $smtp['encryption'];
        $mail->Port       = $smtp['port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($smtp['from_email'], $smtp['from_name'] ?? 'Bagel Boyz NJ');
        $mail->addAddress($to);
        if ($replyTo) $mail->addReplyTo($replyTo);

        $mail->Subject = $subject;
        if ($bodyHtml) {
            $mail->isHTML(true);
            $mail->Body    = $bodyHtml;
            $mail->AltBody = $bodyText;
        } else {
            $mail->Body = $bodyText;
        }

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('BB MAIL: send failed to ' . $to . ' — ' . $mail->ErrorInfo);
        return false;
    }
}

/* =====================================================================
   TEMPLATES
   ===================================================================== */

function bb_order_email_html(array $order, $headline, $message) {
    $cfg  = bb_config();
    $loc  = $cfg['locations'][$order['location_id']] ?? ['name' => '', 'address' => '', 'phone' => ''];
    $site = rtrim(bb_config('site_url', 'https://bagelboyznj.com'), '/');
    $url  = $site . '/track.php?code=' . urlencode($order['order_code']) . '&t=' . urlencode($order['track_token']);
    $e    = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

    $tz = new DateTimeZone(bb_config('timezone', 'America/New_York'));
    $when = 'As soon as possible';
    if (!empty($order['pickup_at'])) {
        $pu = new DateTime($order['pickup_at'], $tz);
        $when = ($order['pickup_type'] === 'scheduled' ? '' : 'Around ') . $pu->format('g:i A \o\n l, M j');
    }

    $rows = '';
    foreach ($order['items'] ?? [] as $it) {
        $opts = [];
        foreach ($it['options'] ?? [] as $o) $opts[] = $o['option_name'];
        $sub = $opts ? '<div style="color:#7F8C8D;font-size:13px;margin-top:3px;">' . $e(implode(' · ', $opts)) . '</div>' : '';
        if (!empty($it['notes'])) {
            $sub .= '<div style="color:#C0392B;font-size:13px;margin-top:3px;">Note: ' . $e($it['notes']) . '</div>';
        }
        $rows .= '<tr>'
            . '<td style="padding:10px 0;border-bottom:1px solid #F5EBD8;vertical-align:top;">'
            . '<strong>' . (int) $it['qty'] . '&times; ' . $e($it['name']) . '</strong>' . $sub . '</td>'
            . '<td style="padding:10px 0;border-bottom:1px solid #F5EBD8;text-align:right;vertical-align:top;white-space:nowrap;">'
            . $e(bb_money($it['line_cents'])) . '</td></tr>';
    }

    $totalsRow = function ($label, $value, $bold = false) use ($e) {
        $w = $bold ? 'font-weight:700;font-size:17px;' : '';
        return '<tr><td style="padding:4px 0;' . $w . '">' . $e($label) . '</td>'
             . '<td style="padding:4px 0;text-align:right;' . $w . '">' . $e($value) . '</td></tr>';
    };

    $totals  = $totalsRow('Subtotal', bb_money($order['subtotal_cents']));
    if ((int) $order['tax_cents'] > 0) $totals .= $totalsRow('Tax', bb_money($order['tax_cents']));
    if ((int) $order['tip_cents'] > 0) $totals .= $totalsRow('Tip', bb_money($order['tip_cents']));
    $totals .= $totalsRow('Total', bb_money($order['total_cents']), true);

    $payLine = $order['payment_status'] === 'paid'
        ? '<p style="margin:0;color:#27AE60;font-weight:600;">Paid online — nothing to pay at pickup.</p>'
        : '<p style="margin:0;color:#C0392B;font-weight:600;">Pay at pickup: ' . $e(bb_money($order['total_cents'])) . '</p>';

    return '<!doctype html><html><body style="margin:0;padding:0;background:#FFF8F0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;color:#1A1A1A;">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8F0;padding:24px 12px;"><tr><td align="center">'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.08);">'

      . '<tr><td style="background:#3E2214;padding:28px 24px;text-align:center;">'
      . '<div style="font-family:Georgia,serif;font-size:26px;font-weight:700;color:#FFF8F0;letter-spacing:.5px;">BAGEL BOYZ NJ</div>'
      . '<div style="color:#D4901E;font-size:13px;margin-top:4px;letter-spacing:1.5px;text-transform:uppercase;">' . $e($loc['name']) . '</div>'
      . '</td></tr>'

      . '<tr><td style="padding:28px 24px 8px;">'
      . '<h1 style="margin:0 0 8px;font-family:Georgia,serif;font-size:24px;">' . $e($headline) . '</h1>'
      . '<p style="margin:0 0 20px;color:#4A4A4A;font-size:15px;line-height:1.6;">' . $e($message) . '</p>'

      . '<div style="background:#FFF8F0;border-radius:12px;padding:16px 18px;margin-bottom:22px;">'
      . '<div style="font-size:12px;letter-spacing:1.5px;text-transform:uppercase;color:#7F8C8D;">Order Number</div>'
      . '<div style="font-size:30px;font-weight:700;color:#D4901E;letter-spacing:1px;margin:2px 0 12px;">' . $e($order['order_code']) . '</div>'
      . '<div style="font-size:12px;letter-spacing:1.5px;text-transform:uppercase;color:#7F8C8D;">Pickup</div>'
      . '<div style="font-size:16px;font-weight:600;margin-bottom:10px;">' . $e($when) . '</div>'
      . '<div style="font-size:14px;color:#4A4A4A;">' . $e($loc['address']) . '<br>' . $e($loc['phone']) . '</div>'
      . '</div>'

      . '<a href="' . $e($url) . '" style="display:block;background:#D4901E;color:#FFFFFF;text-decoration:none;text-align:center;padding:15px;border-radius:10px;font-weight:600;font-size:16px;margin-bottom:24px;">Track Your Order</a>'

      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;">' . $rows . '</table>'
      . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;margin-top:14px;">' . $totals . '</table>'
      . '<div style="margin-top:16px;padding-top:16px;border-top:1px solid #F5EBD8;">' . $payLine . '</div>'
      . '</td></tr>'

      . '<tr><td style="padding:22px 24px 28px;text-align:center;color:#7F8C8D;font-size:13px;line-height:1.6;">'
      . 'Questions? Call us at ' . $e($loc['phone']) . '.<br>'
      . '<a href="' . $e($site) . '" style="color:#D4901E;text-decoration:none;">bagelboyznj.com</a>'
      . '</td></tr>'

      . '</table></td></tr></table></body></html>';
}

/** Receipt to the customer, immediately after the order lands. */
function bb_email_order_receipt(array $order) {
    if (!bb_config('email.send_receipt', true) || empty($order['customer_email'])) return false;

    $first = trim(explode(' ', trim($order['customer_name']))[0]);
    return bb_send_mail(
        $order['customer_email'],
        'Order ' . $order['order_code'] . ' confirmed — Bagel Boyz NJ',
        bb_ticket_text($order, 42),
        bb_order_email_html(
            $order,
            'Thanks, ' . $first . '!',
            'We got your order and the kitchen has it. We\'ll email you the moment it\'s ready for pickup.'
        )
    );
}

/** "Come get it" email when staff taps Ready on the iPad. */
function bb_email_order_ready(array $order) {
    if (!bb_config('email.send_ready', true) || empty($order['customer_email'])) return false;

    $cfg = bb_config();
    $loc = $cfg['locations'][$order['location_id']] ?? ['name' => '', 'address' => ''];
    $first = trim(explode(' ', trim($order['customer_name']))[0]);

    return bb_send_mail(
        $order['customer_email'],
        'Order ' . $order['order_code'] . ' is ready for pickup!',
        bb_ticket_text($order, 42),
        bb_order_email_html(
            $order,
            $first . ', your order is ready!',
            'It\'s bagged and waiting at ' . $loc['name'] . '. Give the counter your order number and it\'s all yours.'
        )
    );
}

/** Internal heads-up to the shop inbox. Belt and braces alongside the iPad. */
function bb_email_store_notify(array $order) {
    if (!bb_config('email.notify_store', true)) return false;

    $to = bb_config('email.store_to');
    if (empty($to)) return false;

    $cfg  = bb_config();
    $loc  = $cfg['locations'][$order['location_id']] ?? ['name' => $order['location_id']];
    $when = $order['pickup_type'] === 'scheduled' && !empty($order['pickup_at'])
        ? (new DateTime($order['pickup_at'], new DateTimeZone(bb_config('timezone', 'America/New_York'))))->format('g:i A, M j')
        : 'ASAP';

    $body  = "NEW ONLINE ORDER — {$loc['name']}\n";
    $body .= str_repeat('=', 46) . "\n\n";
    $body .= "Order:   {$order['order_code']}\n";
    $body .= "Pickup:  {$when}\n";
    $body .= "Payment: " . ($order['payment_status'] === 'paid' ? 'PAID ONLINE' : 'COLLECT ' . bb_money($order['total_cents'])) . "\n\n";
    $body .= bb_ticket_text($order, 46);

    return bb_send_mail(
        $to,
        '[' . $loc['name'] . '] New order ' . $order['order_code'] . ' — ' . $when,
        $body,
        null,
        $order['customer_email'] ?: null
    );
}
