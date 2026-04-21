<?php
/**
 * Bagel Bistro - Contact Form Handler
 * Sends email via SMTP using PHPMailer.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Honeypot
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Thanks! We\'ll be in touch.']);
    exit;
}

$required = ['name', 'email', 'subject', 'message'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
}

$name    = htmlspecialchars(strip_tags(trim($_POST['name'])), ENT_QUOTES, 'UTF-8');
$email   = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$phone   = htmlspecialchars(strip_tags(trim($_POST['phone'] ?? '')), ENT_QUOTES, 'UTF-8');
$subject = htmlspecialchars(strip_tags(trim($_POST['subject'])), ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars(strip_tags(trim($_POST['message'])), ENT_QUOTES, 'UTF-8');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$configPath = __DIR__ . '/smtp-config.php';
if (!file_exists($configPath)) {
    error_log('Contact form: smtp-config.php missing at ' . $configPath);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Email service is not configured yet. Please call (908) 359-7929 or email bagelbistro450@gmail.com directly.'
    ]);
    exit;
}
$config = require $configPath;

$mailSubject = "Contact Form ({$subject}) from {$name}";

$body  = "New message from Bagel Bistro website\n";
$body .= "========================================\n\n";
$body .= "Name: {$name}\n";
$body .= "Email: {$email}\n";
$body .= "Phone: " . ($phone ?: 'Not provided') . "\n";
$body .= "Subject: {$subject}\n\n";
$body .= "Message:\n{$message}\n";
$body .= "\n========================================\n";
$body .= "Submitted: " . date('Y-m-d H:i:s T') . "\n";

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $config['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['username'];
    $mail->Password   = $config['password'];
    $mail->SMTPSecure = $config['encryption'];
    $mail->Port       = $config['port'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['contact_to']);
    $mail->addReplyTo($email, $name);

    $mail->Subject = $mailSubject;
    $mail->Body    = $body;

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Thanks for reaching out! We\'ll get back to you as soon as we can.'
    ]);
} catch (Exception $e) {
    error_log('Contact form SMTP error: ' . $mail->ErrorInfo);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'There was an issue sending your message. Please call us directly at (908) 359-7929.'
    ]);
}
