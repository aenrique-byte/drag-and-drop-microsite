<?php
/**
 * Cron job to calculate publish_at dates for queued chapters based on story schedules
 *
 * This should run frequently (every 5-15 minutes) to keep publish_at dates updated
 * Schedule: */5 * * * * php /path/to/api/cron/calculate-publish-schedule.php
 */

require_once __DIR__ . '/../bootstrap.php';

try {
    // Get all stories that have a schedule assigned and have queued chapters
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id as story_id, s.schedule_id, s.title as story_title,
               ps.name as schedule_name, ps.frequency, ps.time, ps.timezone, ps.days_of_week
        FROM stories s
        INNER JOIN publishing_schedules ps ON s.schedule_id = ps.id
        WHERE ps.active = 1
        AND EXISTS (
            SELECT 1 FROM chapters c
            WHERE c.story_id = s.id AND c.status = 'queued'
        )
    ");
    $stmt->execute();
    $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($stories)) {
        echo "[" . date('Y-m-d H:i:s') . "] No stories with queued chapters and active schedules\n";
        exit(0);
    }

    foreach ($stories as $story) {
        echo "[" . date('Y-m-d H:i:s') . "] Processing: {$story['story_title']} (Schedule: {$story['schedule_name']})\n";

        // Get all queued chapters for this story, ordered by queue_position
        $stmt = $pdo->prepare("
            SELECT id, chapter_number, title, queue_position, publish_at
            FROM chapters
            WHERE story_id = ? AND status = 'queued'
            ORDER BY queue_position ASC, chapter_number ASC
        ");
        $stmt->execute([$story['story_id']]);
        $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($chapters)) {
            continue;
        }

        // Find the last published chapter's publish date, or use now as starting point
        $stmt = $pdo->prepare("
            SELECT MAX(COALESCE(publish_at, updated_at, created_at)) as last_publish
            FROM chapters
            WHERE story_id = ? AND status = 'published'
        ");
        $stmt->execute([$story['story_id']]);
        $lastPublish = $stmt->fetchColumn();

        // Set timezone
        $tz = new DateTimeZone($story['timezone']);
        $now = new DateTime('now', $tz);

        // Start from the later of: last publish date or now
        if ($lastPublish) {
            $nextPublish = new DateTime($lastPublish, $tz);
        } else {
            $nextPublish = clone $now;
        }

        // Parse schedule time
        $timeParts = explode(':', $story['time']);
        $hour = (int)$timeParts[0];
        $minute = (int)$timeParts[1];
        $second = isset($timeParts[2]) ? (int)$timeParts[2] : 0;

        // Parse days of week for weekly schedules
        $daysOfWeek = null;
        if ($story['frequency'] === 'weekly' && $story['days_of_week']) {
            $daysOfWeek = array_map('intval', explode(',', $story['days_of_week']));
            sort($daysOfWeek);
        }

        // Calculate publish_at for each queued chapter
        $updateStmt = $pdo->prepare("
            UPDATE chapters
            SET publish_at = ?
            WHERE id = ?
        ");

        foreach ($chapters as $index => $chapter) {
            // Calculate next publish date
            if ($index === 0) {
                // First chapter: move to next valid publish slot after last publish/now
                $nextPublish = getNextPublishDate($nextPublish, $story['frequency'], $daysOfWeek, $hour, $minute, $second);
            } else {
                // Subsequent chapters: move to next slot after previous chapter
                $nextPublish = getNextPublishDate($nextPublish, $story['frequency'], $daysOfWeek, $hour, $minute, $second);
            }

            // Update the chapter's publish_at
            $publishAtStr = $nextPublish->format('Y-m-d H:i:s');
            $updateStmt->execute([$publishAtStr, $chapter['id']]);

            echo "  - Chapter {$chapter['chapter_number']}: {$chapter['title']} -> {$publishAtStr}\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Schedule calculation complete\n";

} catch (Exception $e) {
    error_log("Schedule calculation error: " . $e->getMessage());
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Get the next publish date based on schedule
 */
function getNextPublishDate(DateTime $from, string $frequency, ?array $daysOfWeek, int $hour, int $minute, int $second): DateTime {
    $next = clone $from;

    if ($frequency === 'daily') {
        // Move to next day at the specified time
        $next->modify('+1 day');
        $next->setTime($hour, $minute, $second);
    } else {
        // Weekly: find next valid day of week
        $currentDayOfWeek = (int)$next->format('w'); // 0=Sunday, 6=Saturday
        $found = false;

        // Try up to 14 days to find next valid day
        for ($i = 1; $i <= 14; $i++) {
            $next->modify('+1 day');
            $testDayOfWeek = (int)$next->format('w');

            if (in_array($testDayOfWeek, $daysOfWeek)) {
                $next->setTime($hour, $minute, $second);
                $found = true;
                break;
            }
        }

        if (!$found) {
            // Fallback: just use next day
            $next->setTime($hour, $minute, $second);
        }
    }

    return $next;
}
