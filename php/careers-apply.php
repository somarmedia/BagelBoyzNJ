<?php
/**
 * Bagel Boyz NJ - Career Application Form Handler
 * Sends email notification when a job application is submitted.
 */

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Honeypot check
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Thank you for applying!']);
    exit;
}

// Required fields
$required = ['name', 'phone', 'age', 'position', 'preferred_location', 'availability'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
}

// Sanitize
$name = htmlspecialchars(strip_tags(trim($_POST['name'])), ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars(strip_tags(trim($_POST['phone'])), ENT_QUOTES, 'UTF-8');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$age = (int) $_POST['age'];
$position = htmlspecialchars(strip_tags(trim($_POST['position'])), ENT_QUOTES, 'UTF-8');
$prefLocation = htmlspecialchars(strip_tags(trim($_POST['preferred_location'])), ENT_QUOTES, 'UTF-8');
$availability = htmlspecialchars(strip_tags(trim($_POST['availability'])), ENT_QUOTES, 'UTF-8');
$experience = htmlspecialchars(strip_tags(trim($_POST['experience'] ?? '')), ENT_QUOTES, 'UTF-8');
$startDate = htmlspecialchars(strip_tags(trim($_POST['start_date'] ?? '')), ENT_QUOTES, 'UTF-8');

// Build email
// TODO: Update this email address to the actual hiring inbox
$to = 'bagelboyz694@gmail.com';
$subject = "Job Application from {$name} - {$position}";

$body = "New Job Application from BagelBoyzNJ.com\n";
$body .= "==========================================\n\n";
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
$body .= "\n==========================================\n";
$body .= "Submitted: " . date('Y-m-d H:i:s T') . "\n";

$headers = "From: noreply@bagelboyznj.com\r\n";
if ($email) {
    $headers .= "Reply-To: {$email}\r\n";
}
$headers .= "X-Mailer: BagelBoyzNJ-CareersForm\r\n";

$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for applying! We\'ll review your application and reach out soon. You can also stop by either location to meet the team.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'There was an issue submitting your application. Please stop by either location in person and ask for the manager.'
    ]);
}
