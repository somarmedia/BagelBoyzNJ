<?php
/**
 * Bagel Boyz NJ - reCAPTCHA v3 token verification.
 *
 * Returns ['ok' => bool, 'message' => string].
 * On success the token is valid AND the score meets the configured threshold.
 */

function bb_verify_recaptcha($token, $expectedAction, $config) {
    if (empty($config['recaptcha_secret']) || $config['recaptcha_secret'] === 'YOUR_RECAPTCHA_V3_SECRET_KEY') {
        error_log('reCAPTCHA: secret not configured — skipping verification');
        return ['ok' => true, 'message' => 'skipped (not configured)'];
    }

    if (empty($token)) {
        return ['ok' => false, 'message' => 'Missing verification token. Please refresh and try again.'];
    }

    $minScore = isset($config['recaptcha_min_score']) ? (float) $config['recaptcha_min_score'] : 0.5;

    $payload = http_build_query([
        'secret'   => $config['recaptcha_secret'],
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('reCAPTCHA: curl error - ' . $curlErr);
        return ['ok' => false, 'message' => 'Could not verify your submission. Please try again.'];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        error_log('reCAPTCHA: invalid JSON from Google - ' . $response);
        return ['ok' => false, 'message' => 'Could not verify your submission. Please try again.'];
    }

    if (empty($data['success'])) {
        $errors = isset($data['error-codes']) ? implode(',', $data['error-codes']) : 'unknown';
        error_log('reCAPTCHA: verification failed - ' . $errors);
        return ['ok' => false, 'message' => 'Verification failed. Please refresh and try again.'];
    }

    if (!empty($expectedAction) && isset($data['action']) && $data['action'] !== $expectedAction) {
        error_log("reCAPTCHA: action mismatch (got {$data['action']}, expected {$expectedAction})");
        return ['ok' => false, 'message' => 'Verification failed. Please refresh and try again.'];
    }

    $score = isset($data['score']) ? (float) $data['score'] : 0.0;
    if ($score < $minScore) {
        error_log("reCAPTCHA: low score {$score} (min {$minScore}) for action " . ($data['action'] ?? '?'));
        return ['ok' => false, 'message' => 'Your submission was flagged as suspicious. If this is a mistake, please call us directly.'];
    }

    return ['ok' => true, 'message' => 'verified', 'score' => $score];
}
