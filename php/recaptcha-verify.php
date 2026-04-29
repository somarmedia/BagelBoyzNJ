<?php
/**
 * Bagel Boyz NJ - reCAPTCHA Enterprise token verification.
 *
 * Calls the Enterprise assessments API:
 *   https://recaptchaenterprise.googleapis.com/v1/projects/{PROJECT_ID}/assessments?key={API_KEY}
 *
 * Returns ['ok' => bool, 'message' => string, ...].
 * On success the token is valid, action matches, AND the score meets the configured threshold.
 */

const BB_RECAPTCHA_SITE_KEY = '6LeYldAsAAAAAL_JmEU0vho10-yvPB7D2WSm9c8P';

function bb_verify_recaptcha($token, $expectedAction, $config) {
    $apiKey    = $config['recaptcha_api_key'] ?? '';
    $projectId = $config['recaptcha_project_id'] ?? '';

    $unconfigured = empty($apiKey) || empty($projectId)
        || $apiKey === 'YOUR_GCP_API_KEY_RESTRICTED_TO_RECAPTCHA_ENTERPRISE'
        || $projectId === 'YOUR_GCP_PROJECT_ID';

    if ($unconfigured) {
        error_log('reCAPTCHA: project_id/api_key not configured — skipping verification');
        return ['ok' => true, 'message' => 'skipped (not configured)'];
    }

    if (empty($token)) {
        return ['ok' => false, 'message' => 'Missing verification token. Please refresh and try again.'];
    }

    $minScore = isset($config['recaptcha_min_score']) ? (float) $config['recaptcha_min_score'] : 0.5;

    $url = "https://recaptchaenterprise.googleapis.com/v1/projects/" . rawurlencode($projectId) . "/assessments?key=" . rawurlencode($apiKey);
    $payload = json_encode([
        'event' => [
            'token'          => $token,
            'expectedAction' => $expectedAction,
            'siteKey'        => BB_RECAPTCHA_SITE_KEY,
            'userIpAddress'  => $_SERVER['REMOTE_ADDR'] ?? '',
        ],
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('reCAPTCHA: curl error - ' . $curlErr);
        return ['ok' => false, 'message' => 'Could not verify your submission. Please try again.'];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        error_log('reCAPTCHA: invalid JSON from Google - ' . substr($response, 0, 500));
        return ['ok' => false, 'message' => 'Could not verify your submission. Please try again.'];
    }

    if ($httpCode !== 200 || isset($data['error'])) {
        $msg = $data['error']['message'] ?? "HTTP {$httpCode}";
        error_log('reCAPTCHA Enterprise API error: ' . $msg);
        return ['ok' => false, 'message' => 'Verification failed. Please refresh and try again.', 'google_response' => $data];
    }

    $tokenProps = $data['tokenProperties'] ?? [];
    if (empty($tokenProps['valid'])) {
        $reason = $tokenProps['invalidReason'] ?? 'unknown';
        error_log('reCAPTCHA: invalid token - ' . $reason);
        return ['ok' => false, 'message' => 'Verification failed. Please refresh and try again.', 'google_response' => $data];
    }

    if (!empty($expectedAction) && isset($tokenProps['action']) && $tokenProps['action'] !== $expectedAction) {
        error_log("reCAPTCHA: action mismatch (got {$tokenProps['action']}, expected {$expectedAction})");
        return ['ok' => false, 'message' => 'Verification failed. Please refresh and try again.', 'google_response' => $data];
    }

    $score = isset($data['riskAnalysis']['score']) ? (float) $data['riskAnalysis']['score'] : 0.0;
    if ($score < $minScore) {
        error_log("reCAPTCHA: low score {$score} (min {$minScore}) for action " . ($tokenProps['action'] ?? '?'));
        return ['ok' => false, 'message' => 'Your submission was flagged as suspicious. If this is a mistake, please call us directly.', 'google_response' => $data];
    }

    return ['ok' => true, 'message' => 'verified', 'score' => $score, 'google_response' => $data];
}
