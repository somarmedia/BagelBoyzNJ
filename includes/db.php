<?php
/**
 * Bagel Boyz NJ — config loader + database connection.
 *
 * CONFIG comes from two files:
 *   includes/order-settings.php   committed, deploys with git, no secrets
 *   includes/order-config.php     gitignored, uploaded by hand, optional
 * The second is deep-merged over the first, so it only needs the keys it
 * actually overrides.
 *
 * DATABASE supports SQLite (default — zero setup, file created on first use)
 * and MySQL. All application SQL is written in MySQL dialect; bb_sql()
 * translates it for SQLite, and BB_PDO applies that automatically so call
 * sites never think about it.
 */

if (!function_exists('bb_config')) {

    /** Recursive array merge where $over wins, but only for keys it defines. */
    function bb_config_merge(array $base, array $over) {
        foreach ($over as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])
                && array_keys($v) !== range(0, count($v) - 1)) {
                // Associative array → merge key by key.
                $base[$k] = bb_config_merge($base[$k], $v);
            } else {
                // Scalar or list → replace outright.
                $base[$k] = $v;
            }
        }
        return $base;
    }

    function bb_config($key = null, $default = null) {
        static $config = null;

        if ($config === null) {
            $settingsPath = __DIR__ . '/order-settings.php';
            $secretsPath  = __DIR__ . '/order-config.php';

            if (file_exists($settingsPath)) {
                $config = require $settingsPath;
            } else {
                // Should never happen — order-settings.php is committed.
                error_log('BB ORDERING: includes/order-settings.php is missing.');
                $config = [];
            }

            // Optional private overlay for real credentials.
            $hasSecrets = file_exists($secretsPath);
            if ($hasSecrets) {
                $secrets = require $secretsPath;
                if (is_array($secrets)) {
                    $config = bb_config_merge($config, $secrets);
                }
            }

            $config['_configured']  = !empty($config['locations']);
            $config['_has_secrets'] = $hasSecrets;

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

    function bb_db_driver() {
        return bb_config('db.driver', 'sqlite') === 'mysql' ? 'mysql' : 'sqlite';
    }

    /* =====================================================================
       SQL PORTABILITY
       ---------------------------------------------------------------------
       Application SQL is written once, in MySQL dialect. When running on
       SQLite this rewrites the handful of constructs that differ.

       Time functions become PHP-generated literals rather than SQLite's own
       date functions, because SQLite's CURRENT_TIMESTAMP is UTC while MySQL's
       NOW() honours the session timezone. Generating the literal in PHP keeps
       both drivers on America/New_York and stays correct across DST.
       ===================================================================== */
    function bb_sql($sql) {
        if (bb_db_driver() !== 'sqlite') return $sql;

        $now = time();
        $q   = function ($ts) { return "'" . date('Y-m-d H:i:s', $ts) . "'"; };

        // DATE_SUB / DATE_ADD before NOW(), since they contain it.
        $sql = preg_replace_callback(
            '/DATE_SUB\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+(MINUTE|HOUR|DAY)\s*\)/i',
            function ($m) use ($now, $q) {
                $mult = ['MINUTE' => 60, 'HOUR' => 3600, 'DAY' => 86400];
                return $q($now - ((int) $m[1] * $mult[strtoupper($m[2])]));
            }, $sql);

        $sql = preg_replace_callback(
            '/DATE_ADD\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+(MINUTE|HOUR|DAY)\s*\)/i',
            function ($m) use ($now, $q) {
                $mult = ['MINUTE' => 60, 'HOUR' => 3600, 'DAY' => 86400];
                return $q($now + ((int) $m[1] * $mult[strtoupper($m[2])]));
            }, $sql);

        $sql = preg_replace('/\bNOW\(\)/i',    $q($now), $sql);
        $sql = preg_replace('/\bCURDATE\(\)/i', "'" . date('Y-m-d', $now) . "'", $sql);

        $sql = preg_replace('/\bINSERT\s+IGNORE\s+INTO\b/i', 'INSERT OR IGNORE INTO', $sql);

        // ON DUPLICATE KEY UPDATE → ON CONFLICT(cols) DO UPDATE SET.
        // SQLite needs the conflict target named, so map it per table.
        if (preg_match('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i', $sql)) {
            $conflictCols = [
                'bb_rate_limit'        => 'bucket_key',
                'bb_item_availability' => 'location_id, menu_item_id',
                'bb_store_state'       => 'location_id',
            ];
            $target = '';
            if (preg_match('/INSERT(?:\s+OR\s+IGNORE)?\s+INTO\s+`?(\w+)`?/i', $sql, $m)
                && isset($conflictCols[$m[1]])) {
                $target = '(' . $conflictCols[$m[1]] . ')';
            }
            $sql = preg_replace('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b/i',
                                'ON CONFLICT' . $target . ' DO UPDATE SET', $sql);
        }

        return $sql;
    }

    /**
     * PDO wrapper that runs every statement through bb_sql().
     * Keeps the translation in one place instead of at ~40 call sites.
     */
    class BB_PDO extends PDO
    {
        #[\ReturnTypeWillChange]
        public function prepare($query, $options = [])
        {
            return parent::prepare(bb_sql($query), $options);
        }

        #[\ReturnTypeWillChange]
        public function exec($statement)
        {
            return parent::exec(bb_sql($statement));
        }

        #[\ReturnTypeWillChange]
        public function query($query, ...$args)
        {
            return parent::query(bb_sql($query), ...$args);
        }
    }

    /**
     * Shared database handle. Throws on failure — callers talking to a
     * browser should catch and show a friendly "call us" message rather
     * than leaking connection details.
     */
    function bb_db() {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $driver = bb_db_driver();

        if ($driver === 'sqlite') {
            $path = bb_config('db.sqlite_path', __DIR__ . '/../db/bagelboyz.sqlite');

            $dir = dirname($path);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            $isNew = !file_exists($path) || filesize($path) === 0;

            $pdo = new BB_PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $pdo->exec('PRAGMA foreign_keys = ON');
            // WAL lets the KDS poll while an order is being written.
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');

            if ($isNew) bb_sqlite_init($pdo);

            return $pdo;
        }

        /* ---- MySQL ---- */
        $db  = bb_config('db');
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
            $db['host'] ?? 'localhost', $db['name'] ?? '', $db['charset'] ?? 'utf8mb4');

        $pdo = new BB_PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Keep MySQL's clock aligned with PHP's, or NOW() drifts from date().
        try {
            $tz = new DateTime('now', new DateTimeZone(bb_config('timezone', 'America/New_York')));
            $pdo->exec("SET time_zone = '" . $tz->format('P') . "'");
        } catch (Exception $e) {
            error_log('BB ORDERING: could not set MySQL time_zone — ' . $e->getMessage());
        }

        return $pdo;
    }

    /** Create the schema on a brand-new SQLite file. */
    function bb_sqlite_init(PDO $pdo) {
        $schema = __DIR__ . '/../db/schema.sqlite.sql';
        if (!file_exists($schema)) {
            error_log('BB ORDERING: db/schema.sqlite.sql missing — cannot initialise database.');
            return false;
        }

        try {
            $sql = file_get_contents($schema);

            // Strip -- comments FIRST, then split. Doing it the other way
            // round means a chunk that merely starts with a comment gets
            // discarded along with the CREATE TABLE that follows it.
            $sql = preg_replace('/^\s*--[^\r\n]*$/m', '', $sql);

            foreach (explode(';', $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') continue;
                $pdo->exec($stmt);
            }

            error_log('BB ORDERING: initialised new SQLite database.');
            return true;
        } catch (Throwable $e) {
            error_log('BB ORDERING: SQLite init failed — ' . $e->getMessage());
            return false;
        }
    }

    /** Do the ordering tables exist yet? */
    function bb_db_tables(PDO $pdo) {
        $names = [];
        try {
            if (bb_db_driver() === 'sqlite') {
                foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table'") as $r) {
                    $names[] = $r['name'];
                }
            } else {
                foreach ($pdo->query('SHOW TABLES') as $r) {
                    $names[] = array_values($r)[0];
                }
            }
        } catch (Throwable $e) {
            error_log('BB ORDERING: table listing failed — ' . $e->getMessage());
        }
        return $names;
    }

    /** Load the canonical menu (data/menu.php) once per request. */
    function bb_menu() {
        static $menu = null;
        if ($menu === null) $menu = require __DIR__ . '/../data/menu.php';
        return $menu;
    }
}
