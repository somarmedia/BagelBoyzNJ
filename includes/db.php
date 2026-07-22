<?php
/**
 * Bagel Boyz NJ — config loader + PDO connection.
 * Everything server-side goes through bb_config() and bb_db().
 */

if (!function_exists('bb_config')) {

    /**
     * Load includes/order-config.php once. Falls back to the .example file
     * so a fresh checkout doesn't fatal — it just runs with placeholder values
     * and no database, which bb_db() will report clearly.
     */
    function bb_config($key = null, $default = null) {
        static $config = null;

        if ($config === null) {
            $path = __DIR__ . '/order-config.php';
            $live = file_exists($path);

            if (!$live) {
                // Fall back to the example so pages render instead of fataling,
                // but mark it. The example file is COMMITTED TO THE REPO, so its
                // kds.pin and printing.poll_key are public knowledge — anything
                // security-sensitive must refuse to operate when _configured is
                // false rather than trust these placeholder values.
                error_log('BB ORDERING: includes/order-config.php is missing — copy order-config.example.php and fill it in.');
                $path = __DIR__ . '/order-config.example.php';
            }

            $config = require $path;
            $config['_configured'] = $live;

            if (!empty($config['timezone'])) {
                date_default_timezone_set($config['timezone']);
            }
        }

        if ($key === null) return $config;

        // Dot path: bb_config('stripe.secret_key')
        $node = $config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) return $default;
            $node = $node[$part];
        }
        return $node;
    }

    /**
     * Shared PDO handle. Throws PDOException on failure — callers that talk to
     * the browser should catch it and return a friendly "call us" message
     * rather than leaking connection details.
     */
    function bb_db() {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $db = bb_config('db');
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $db['host'] ?? 'localhost',
            $db['name'] ?? '',
            $db['charset'] ?? 'utf8mb4'
        );

        $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements, so LIMIT/OFFSET bind correctly and
            // there is no emulation layer to trip over.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // MySQL session TZ must match PHP's, or NOW() drifts from date().
        try {
            $tz = new DateTime('now', new DateTimeZone(bb_config('timezone', 'America/New_York')));
            $pdo->exec("SET time_zone = '" . $tz->format('P') . "'");
        } catch (Exception $e) {
            error_log('BB ORDERING: could not set MySQL time_zone — ' . $e->getMessage());
        }

        return $pdo;
    }

    /** Load the canonical menu (data/menu.php) once per request. */
    function bb_menu() {
        static $menu = null;
        if ($menu === null) $menu = require __DIR__ . '/../data/menu.php';
        return $menu;
    }
}
