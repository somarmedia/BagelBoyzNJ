<?php
/**
 * SMTP configuration for Bagel Bistro form handlers.
 *
 * Copy this file to `smtp-config.php` and fill in real values.
 * `smtp-config.php` should be gitignored so credentials stay out of version control.
 *
 * For Gmail, you'll need an App Password (not your regular password):
 *   https://myaccount.google.com/apppasswords
 *
 * If the demo site stays under bagelboyznj.com, using the Hostinger mailbox
 * setup is simpler — replace the Gmail section below with the Hostinger SMTP
 * details (same shape as the main site's smtp-config).
 */

return [
    // Gmail SMTP (for bagelbistro450@gmail.com)
    'host'       => 'smtp.gmail.com',
    'port'       => 465,
    'encryption' => 'ssl',
    'username'   => 'bagelbistro450@gmail.com',
    'password'   => 'YOUR_GMAIL_APP_PASSWORD_HERE',
    'from_email' => 'bagelbistro450@gmail.com',
    'from_name'  => 'Bagel Bistro Website',

    // All inquiries route to the same mailbox for the demo
    'contact_to'  => 'bagelbistro450@gmail.com',
    'catering_to' => 'bagelbistro450@gmail.com',
    'careers_to'  => 'bagelbistro450@gmail.com',
];
