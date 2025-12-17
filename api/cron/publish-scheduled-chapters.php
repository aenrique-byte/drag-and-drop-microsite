<?php
/**
 * Cron job to auto-publish scheduled chapters
 *
 * Schedule this to run every minute or every 5 minutes via cron:
 * * * * * * php /path/to/api/cron/publish-scheduled-chapters.php
 * or
 * */5 * * * * php /path/to/api/cron/publish-scheduled-chapters.php
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    // Find all queued chapters where publish_at has passed
    $stmt = $pdo->prepare("
        SELECT id, story_id, chapter_number, title, publish_at, queue_position
        FROM chapters
        WHERE status = 'queued'
        AND publish_at IS NOT NULL
        AND publish_at <= NOW()
        ORDER BY publish_at ASC, queue_position ASC
    ");
    $stmt->execute();
    $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($chapters)) {
        echo "[" . date('Y-m-d H:i:s') . "] No chapters to publish\n";
        exit(0);
    }

    // Publish each chapter and clear queue_position
    $stmt = $pdo->prepare("
        UPDATE chapters
        SET status = 'published', queue_position = NULL
        WHERE id = ?
    ");

    $publishedCount = 0;
    foreach ($chapters as $chapter) {
        $stmt->execute([$chapter['id']]);
        echo "[" . date('Y-m-d H:i:s') . "] Published: Chapter {$chapter['chapter_number']}: {$chapter['title']} (ID: {$chapter['id']}, Scheduled: {$chapter['publish_at']})\n";
        $publishedCount++;
    }

    echo "[" . date('Y-m-d H:i:s') . "] Total published: {$publishedCount} chapter(s)\n";

} catch (Exception $e) {
    error_log("Scheduled chapter publishing error: " . $e->getMessage());
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
