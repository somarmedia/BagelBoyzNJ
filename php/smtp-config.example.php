<?php
/**
 * SMTP configuration for Bagel Boyz NJ form handlers.
 *
 * Copy this file to `smtp-config.php` and fill in the real values.
 * `smtp-config.php` is gitignored so credentials stay out of version control.
 */

return [
    'host'       => 'smtp.hostinger.com',
    'port'       => 465,
    'encryption' => 'ssl',
    'username'   => 'catering@bagelboyznj.com',
    'password'   => 'YOUR_MAILBOX_PASSWORD_HERE',
    'from_email' => 'catering@bagelboyznj.com',
    'from_name'  => 'Bagel Boyz NJ Website',
    'catering_to' => 'catering@bagelboyznj.com',
    'careers_to'  => 'jobs@bagelboyznj.com',

    'recaptcha_project_id' => 'YOUR_GCP_PROJECT_ID',
    'recaptcha_api_key'    => 'YOUR_GCP_API_KEY_RESTRICTED_TO_RECAPTCHA_ENTERPRISE',
    'recaptcha_min_score'  => 0.5,
];
