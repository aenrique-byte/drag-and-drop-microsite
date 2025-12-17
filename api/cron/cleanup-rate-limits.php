<?php
/**
 * Rate Limit Cleanup Script
 * Run daily via cron to clean up old rate limit entries
 *
 * Cron: 0 2 * * * /usr/bin/php /path/to/api/cron/cleanup-rate-limits.php
 *
 * Keeps last 24 hours of data, removes older entries
 */

require_once __DIR__ . '/../bootstrap.php';

$cutoff = time() - 86400; // 24 hours ago

try {
    // Start transaction for safety
    $pdo->beginTransaction();

    // Preview what will be deleted
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, MIN(window_start) as oldest, MAX(window_start) as newest
        FROM rate_limit_agg
        WHERE window_start < ?
    ");
    $stmt->execute([$cutoff]);
    $preview = $stmt->fetch();

    if ($preview['count'] > 0) {
        echo sprintf(
            "Found %d rows to delete (oldest: %s, newest: %s)\n",
            $preview['count'],
            date('Y-m-d H:i:s', $preview['oldest']),
            date('Y-m-d H:i:s', $preview['newest'])
        );

        // Delete old entries
        $stmt = $pdo->prepare("DELETE FROM rate_limit_agg WHERE window_start < ?");
        $stmt->execute([$cutoff]);
        $deletedRows = $stmt->rowCount();

        // Commit transaction
        $pdo->commit();

        // Log structured event
        error_log(json_encode([
            'event' => 'rate_limit_cleanup',
            'deleted_rows' => $deletedRows,
            'cutoff_timestamp' => $cutoff,
            'ts' => time()
        ]));

        echo "Successfully deleted {$deletedRows} old rate limit entries\n";
    } else {
        $pdo->commit();
        echo "No old entries to clean up\n";
    }

    // Optional: Show current table stats
    $stmt = $pdo->query("
        SELECT COUNT(*) as total_rows,
               COUNT(DISTINCT key_name) as unique_keys,
               MIN(window_start) as oldest,
               MAX(window_start) as newest
        FROM rate_limit_agg
    ");
    $stats = $stmt->fetch();

    echo sprintf(
        "Current table: %d rows, %d unique keys, range: %s to %s\n",
        $stats['total_rows'],
        $stats['unique_keys'],
        date('Y-m-d H:i:s', $stats['oldest'] ?? 0),
        date('Y-m-d H:i:s', $stats['newest'] ?? 0)
    );

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(json_encode([
        'event' => 'rate_limit_cleanup_error',
        'error' => $e->getMessage(),
        'ts' => time()
    ]));

    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
