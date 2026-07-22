<?php
/**
 * Bagel Boyz NJ — shared print-queue helpers.
 *
 * Used by every print driver (Star CloudPRNT, Star WebPRNT, Epson SDP) so
 * that claiming and retiring jobs behaves identically no matter which
 * printer is on the counter.
 */

require_once __DIR__ . '/../includes/order-lib.php';

if (!function_exists('bb_claim_next_job')) {

    /**
     * Atomically take the oldest queued job for a location.
     *
     * The conditional UPDATE (`AND status = 'queued'`) is what makes this safe
     * when two printers — or one printer retrying — poll at the same instant.
     * Only one caller can flip the row, so a ticket can never print twice.
     */
    function bb_claim_next_job(PDO $pdo, $location, $claimedBy) {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $st = $pdo->prepare(
                "SELECT * FROM bb_print_jobs
                  WHERE location_id = ? AND status = 'queued'
                  ORDER BY created_at ASC, id ASC LIMIT 1"
            );
            $st->execute([$location]);
            $job = $st->fetch();
            if (!$job) return null;

            $upd = $pdo->prepare(
                "UPDATE bb_print_jobs
                    SET status = 'claimed', claimed_by = ?, claimed_at = NOW(), attempts = attempts + 1
                  WHERE id = ? AND status = 'queued'"
            );
            $upd->execute([$claimedBy, $job['id']]);

            if ($upd->rowCount() === 1) {
                $job['status'] = 'claimed';
                return $job;
            }
            // Lost the race for that row; loop and try the next one.
        }
        return null;
    }

    /** Release jobs a printer claimed but never confirmed (power cut mid-print). */
    function bb_requeue_stale_jobs(PDO $pdo, $location, $minutes = 3) {
        // MySQL won't reliably accept a bound parameter as the INTERVAL
        // quantity across versions, so this one is cast and interpolated.
        // It's an internal int, never user input — no injection surface.
        $mins = max(1, (int) $minutes);
        $pdo->prepare(
            "UPDATE bb_print_jobs
                SET status = 'queued', claimed_by = NULL, claimed_at = NULL
              WHERE location_id = ? AND status = 'claimed'
                AND claimed_at < DATE_SUB(NOW(), INTERVAL {$mins} MINUTE)"
        )->execute([$location]);
    }

    /** Mark a claimed job done (or failed) and reflect it on the order row. */
    function bb_complete_job(PDO $pdo, array $job, $resultCode = '') {
        $ok = ($resultCode === '' || strpos((string) $resultCode, '2') === 0);

        $pdo->prepare(
            "UPDATE bb_print_jobs SET status = ?, done_at = NOW(), last_error = ? WHERE id = ?"
        )->execute([$ok ? 'done' : 'failed', $ok ? null : ('printer code ' . $resultCode), $job['id']]);

        if ($ok) {
            $pdo->prepare("UPDATE bb_orders SET print_status = 'printed', printed_at = NOW() WHERE id = ?")
                ->execute([$job['order_id']]);
            bb_log_event($pdo, $job['order_id'], 'printed', $job['claimed_by'], 'printer');
        } else {
            $pdo->prepare("UPDATE bb_orders SET print_status = 'failed' WHERE id = ?")
                ->execute([$job['order_id']]);
            bb_log_event($pdo, $job['order_id'], 'print_failed', 'code ' . $resultCode, 'printer');
        }
    }
}
