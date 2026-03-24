<?php
/**
 * Bagel Boyz NJ - Catering Inquiry Form Handler
 * Sends email notification when a catering inquiry is submitted.
 */

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Honeypot check (anti-spam)
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Thank you! We\'ll be in touch soon.']);
    exit;
}

// Required fields
$required = ['name', 'email', 'phone', 'event_date', 'guest_count'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
}

// Sanitize inputs
$name = htmlspecialchars(strip_tags(trim($_POST['name'])), ENT_QUOTES, 'UTF-8');
$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$phone = htmlspecialchars(strip_tags(trim($_POST['phone'])), ENT_QUOTES, 'UTF-8');
$eventDate = htmlspecialchars(strip_tags(trim($_POST['event_date'])), ENT_QUOTES, 'UTF-8');
$eventTime = htmlspecialchars(strip_tags(trim($_POST['event_time'] ?? '')), ENT_QUOTES, 'UTF-8');
$guestCount = (int) $_POST['guest_count'];
$package = htmlspecialchars(strip_tags(trim($_POST['package'] ?? '')), ENT_QUOTES, 'UTF-8');
$location = htmlspecialchars(strip_tags(trim($_POST['location'] ?? '')), ENT_QUOTES, 'UTF-8');
$details = htmlspecialchars(strip_tags(trim($_POST['details'] ?? '')), ENT_QUOTES, 'UTF-8');

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

// Build email
// TODO: Update this email address to the actual catering inbox
$to = 'bagelboyz694@gmail.com';
$subject = "Catering Inquiry from {$name} - {$guestCount} guests on {$eventDate}";

$body = "New Catering Inquiry from BagelBoyzNJ.com\n";
$body .= "==========================================\n\n";
$body .= "Name: {$name}\n";
$body .= "Email: {$email}\n";
$body .= "Phone: {$phone}\n";
$body .= "Event Date: {$eventDate}\n";
$body .= "Event Time: " . ($eventTime ?: 'Not specified') . "\n";
$body .= "Number of Guests: {$guestCount}\n";
$body .= "Package: " . ($package ?: 'Not selected') . "\n";
$body .= "Preferred Location: " . ($location ?: 'Either') . "\n";
$body .= "\nAdditional Details:\n";
$body .= ($details ?: 'None provided') . "\n";
$body .= "\n==========================================\n";
$body .= "Submitted: " . date('Y-m-d H:i:s T') . "\n";

$headers = "From: noreply@bagelboyznj.com\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "X-Mailer: BagelBoyzNJ-CateringForm\r\n";

// Send email
$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! We received your catering inquiry and will get back to you within 24 hours.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'There was an issue sending your inquiry. Please call us directly at (732) 646-4455.'
    ]);
}
