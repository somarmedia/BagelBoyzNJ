<?php
/**
 * Bagel Bistro - Catering Inquiry Form Handler
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

if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Thanks! We\'ll be in touch soon.']);
    exit;
}

$required = ['name', 'email', 'phone', 'event_date', 'guest_count'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
}

$name       = htmlspecialchars(strip_tags(trim($_POST['name'])), ENT_QUOTES, 'UTF-8');
$email      = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$phone      = htmlspecialchars(strip_tags(trim($_POST['phone'])), ENT_QUOTES, 'UTF-8');
$eventDate  = htmlspecialchars(strip_tags(trim($_POST['event_date'])), ENT_QUOTES, 'UTF-8');
$eventTime  = htmlspecialchars(strip_tags(trim($_POST['event_time'] ?? '')), ENT_QUOTES, 'UTF-8');
$guestCount = (int) $_POST['guest_count'];
$package    = htmlspecialchars(strip_tags(trim($_POST['package'] ?? '')), ENT_QUOTES, 'UTF-8');
$details    = htmlspecialchars(strip_tags(trim($_POST['details'] ?? '')), ENT_QUOTES, 'UTF-8');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$configPath = __DIR__ . '/smtp-config.php';
if (!file_exists($configPath)) {
    error_log('Catering form: smtp-config.php missing at ' . $configPath);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Email service is not configured yet. Please call (908) 359-7929 or email bagelbistro450@gmail.com directly.'
    ]);
    exit;
}
$config = require $configPath;

$subject = "Catering Inquiry from {$name} - {$guestCount} guests on {$eventDate}";

$body  = "New Catering Inquiry from Bagel Bistro website\n";
$body .= "================================================\n\n";
$body .= "Name: {$name}\n";
$body .= "Email: {$email}\n";
$body .= "Phone: {$phone}\n";
$body .= "Event Date: {$eventDate}\n";
$body .= "Event Time: " . ($eventTime ?: 'Not specified') . "\n";
$body .= "Number of Guests: {$guestCount}\n";
$body .= "Package: " . ($package ?: 'Not selected') . "\n";
$body .= "\nAdditional Details:\n";
$body .= ($details ?: 'None provided') . "\n";
$body .= "\n================================================\n";
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
    $mail->addAddress($config['catering_to']);
    $mail->addReplyTo($email, $name);

    $mail->Subject = $subject;
    $mail->Body    = $body;

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Thanks! We received your catering inquiry and will get back to you within 24 hours.'
    ]);
} catch (Exception $e) {
    error_log('Catering form SMTP error: ' . $mail->ErrorInfo);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'There was an issue sending your inquiry. Please call us directly at (908) 359-7929.'
    ]);
}
