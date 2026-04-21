<?php
/**
 * Bagel Bistro - Career Application Form Handler
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
    echo json_encode(['success' => true, 'message' => 'Thanks for applying!']);
    exit;
}

$required = ['name', 'phone', 'age', 'position', 'availability'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
}

$name         = htmlspecialchars(strip_tags(trim($_POST['name'])), ENT_QUOTES, 'UTF-8');
$phone        = htmlspecialchars(strip_tags(trim($_POST['phone'])), ENT_QUOTES, 'UTF-8');
$email        = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$age          = (int) $_POST['age'];
$position     = htmlspecialchars(strip_tags(trim($_POST['position'])), ENT_QUOTES, 'UTF-8');
$prefLocation = htmlspecialchars(strip_tags(trim($_POST['preferred_location'] ?? '450 Amwell Rd, Hillsborough')), ENT_QUOTES, 'UTF-8');
$availability = htmlspecialchars(strip_tags(trim($_POST['availability'])), ENT_QUOTES, 'UTF-8');
$experience   = htmlspecialchars(strip_tags(trim($_POST['experience'] ?? '')), ENT_QUOTES, 'UTF-8');
$startDate    = htmlspecialchars(strip_tags(trim($_POST['start_date'] ?? '')), ENT_QUOTES, 'UTF-8');

$configPath = __DIR__ . '/smtp-config.php';
if (!file_exists($configPath)) {
    error_log('Careers form: smtp-config.php missing at ' . $configPath);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Application service is not configured yet. Please stop by 450 Amwell Rd or call (908) 359-7929.'
    ]);
    exit;
}
$config = require $configPath;

$subject = "Job Application from {$name} - {$position}";

$body  = "New Job Application from Bagel Bistro website\n";
$body .= "================================================\n\n";
$body .= "Name: {$name}\n";
$body .= "Phone: {$phone}\n";
$body .= "Email: " . ($email ?: 'Not provided') . "\n";
$body .= "Age: {$age}\n";
$body .= "Position: {$position}\n";
$body .= "Preferred Location: {$prefLocation}\n";
$body .= "Availability: {$availability}\n";
$body .= "Can Start: " . ($startDate ?: 'Not specified') . "\n";
$body .= "\nExperience:\n";
$body .= ($experience ?: 'None listed') . "\n";
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
    $mail->addAddress($config['careers_to']);
    if ($email) {
        $mail->addReplyTo($email, $name);
    }

    $mail->Subject = $subject;
    $mail->Body    = $body;

    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Thanks for applying! We\'ll review your application and reach out soon. You can also stop by 450 Amwell Rd and ask for the manager.'
    ]);
} catch (Exception $e) {
    error_log('Careers form SMTP error: ' . $mail->ErrorInfo);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'There was an issue submitting your application. Please stop by 450 Amwell Rd and ask for the manager.'
    ]);
}
