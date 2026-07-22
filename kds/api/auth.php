<?php
/**
 * POST /kds/api/auth.php   { action: 'login'|'logout'|'check', pin, location, device }
 */

require_once __DIR__ . '/../_auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bb_kds_json($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$in     = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $in['action'] ?? 'check';

try {
    $cfg = bb_config();

    if ($action === 'check') {
        $session = bb_kds_session();
        bb_kds_json([
            'success'     => true,
            'signed_in'   => (bool) $session,
            'location'    => $session['location_id'] ?? null,
            'device'      => $session['device_label'] ?? null,
        ]);
    }

    if ($action === 'logout') {
        bb_kds_logout();
        bb_kds_json(['success' => true, 'signed_in' => false]);
    }

    if ($action === 'login') {
        // Slow down PIN guessing. Six tries per 15 minutes per IP is plenty
        // for a real staff member fat-fingering it.
        require_once __DIR__ . '/../../api/_bootstrap.php';
        if (!bb_rate_limit('kdspin:' . bb_client_ip(), 6, 900)) {
            bb_kds_json(['success' => false, 'message' => 'Too many attempts. Wait a few minutes and try again.'], 429);
        }

        $pin      = (string) ($in['pin'] ?? '');
        $location = (string) ($in['location'] ?? '');
        $device   = (string) ($in['device'] ?? '');

        if (!isset($cfg['locations'][$location])) {
            bb_kds_json(['success' => false, 'message' => 'Please choose a location.'], 400);
        }

        $token = bb_kds_login($pin, $location, $device);
        if (!$token) {
            bb_kds_json(['success' => false, 'message' => 'Wrong PIN.'], 401);
        }

        bb_kds_json([
            'success'   => true,
            'signed_in' => true,
            'location'  => $location,
            'location_name' => $cfg['locations'][$location]['name'],
        ]);
    }

    bb_kds_json(['success' => false, 'message' => 'Unknown action.'], 400);

} catch (Throwable $e) {
    error_log('BB KDS auth: ' . $e->getMessage());
    bb_kds_json(['success' => false, 'message' => 'Sign-in is temporarily unavailable.'], 500);
}
