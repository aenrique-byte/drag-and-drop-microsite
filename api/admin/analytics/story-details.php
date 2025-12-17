<?php
require_once '../../bootstrap.php';

// Check authentication - session already started in bootstrap.php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json');

try {
    global $pdo;

    // Get parameters
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    $story_id = isset($_GET['story_id']) ? (int)$_GET['story_id'] : null;
    $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    if (!$story_id) {
        // Return list of all published stories with basic analytics
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.slug,
                s.title,
                s.status,
                COUNT(DISTINCT c.id) as total_chapters,
                COALESCE(SUM(c.like_count), 0) as total_likes,
                COALESCE(SUM(c.comment_count), 0) as total_comments,
                (
                    SELECT COUNT(DISTINCT ae1.id)
                    FROM analytics_events ae1
                    WHERE ae1.event_type = 'story_view'
                        AND ae1.content_type = 'story'
                        AND ae1.content_id = s.id
                        AND ae1.created_at >= ?
                ) as story_views,
                (
                    SELECT COUNT(DISTINCT ae2.id)
                    FROM analytics_events ae2
                    WHERE ae2.event_type = 'chapter_view'
                        AND ae2.parent_type = 'story'
                        AND ae2.parent_id = s.id
                        AND ae2.created_at >= ?
                ) as chapter_views,
                (
                    SELECT COUNT(DISTINCT ae3.session_id)
                    FROM analytics_events ae3
                    WHERE ae3.event_type = 'chapter_view'
                        AND ae3.parent_type = 'story'
                        AND ae3.parent_id = s.id
                        AND ae3.created_at >= ?
                ) as unique_readers,
                (
                    SELECT AVG(ae4.value_num) * 100
                    FROM analytics_events ae4
                    WHERE ae4.event_type = 'chapter_depth'
                        AND ae4.parent_type = 'story'
                        AND ae4.parent_id = s.id
                        AND ae4.created_at >= ?
                ) as avg_reading_depth
            FROM stories s
            LEFT JOIN chapters c ON c.story_id = s.id
            WHERE s.status = 'published'
            GROUP BY s.id, s.slug, s.title, s.status
            ORDER BY s.title ASC
        ");
        $stmt->execute([$startDate, $startDate, $startDate, $startDate]);
        $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data
        foreach ($stories as &$story) {
            $story['id'] = (int)$story['id'];
            $story['total_chapters'] = (int)$story['total_chapters'];
            $story['total_likes'] = (int)$story['total_likes'];
            $story['total_comments'] = (int)$story['total_comments'];
            $story['story_views'] = (int)$story['story_views'];
            $story['chapter_views'] = (int)$story['chapter_views'];
            $story['unique_readers'] = (int)$story['unique_readers'];
            $story['total_views'] = $story['story_views'] + $story['chapter_views'];
            // avg_reading_depth is already converted to percentage (0-100) in SQL
            $story['avg_reading_depth'] = $story['avg_reading_depth'] ? round((float)$story['avg_reading_depth'], 0) : 0;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'stories' => $stories,
                'period_days' => $days
            ]
        ]);
    } else {
        // Return detailed chapter analytics for specific story
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.title,
                c.chapter_number,
                c.slug as chapter_slug,
                c.status,
                c.like_count as likes,
                c.comment_count as comments,
                COUNT(DISTINCT CASE WHEN ae.event_type = 'chapter_view' THEN ae.id END) as views,
                COUNT(DISTINCT CASE WHEN ae.event_type = 'chapter_view' THEN ae.session_id END) as unique_readers,
                AVG(CASE WHEN ae.event_type = 'chapter_depth' THEN ae.value_num END) * 100 as avg_reading_depth,
                AVG(CASE WHEN ae.event_type = 'chapter_depth' THEN JSON_EXTRACT(ae.meta_json, '$.time_ms') END) / 1000 as avg_time_spent
            FROM chapters c
            LEFT JOIN analytics_events ae ON ae.content_id = c.id AND ae.content_type = 'chapter'
                AND ae.created_at >= ?
            WHERE c.story_id = ?
            GROUP BY c.id, c.title, c.chapter_number, c.slug, c.status, c.like_count, c.comment_count
            ORDER BY c.chapter_number ASC
        ");
        $stmt->execute([$startDate, $story_id]);
        $chapters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data
        foreach ($chapters as &$chapter) {
            $chapter['id'] = (int)$chapter['id'];
            $chapter['chapter_number'] = (int)$chapter['chapter_number'];
            $chapter['likes'] = (int)$chapter['likes'];
            $chapter['comments'] = (int)$chapter['comments'];
            $chapter['views'] = (int)$chapter['views'];
            $chapter['unique_readers'] = (int)$chapter['unique_readers'];
            // avg_reading_depth is already converted to percentage (0-100) in SQL
            $chapter['avg_reading_depth'] = $chapter['avg_reading_depth'] ? round((float)$chapter['avg_reading_depth'], 0) : 0;
            // avg_time_spent is already converted to seconds in SQL
            $chapter['avg_time_spent'] = $chapter['avg_time_spent'] ? round((float)$chapter['avg_time_spent'], 0) : 0;
        }

        // Get story info
        $stmt = $pdo->prepare("SELECT id, slug, title FROM stories WHERE id = ?");
        $stmt->execute([$story_id]);
        $story = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'story' => $story,
                'chapters' => $chapters,
                'period_days' => $days
            ]
        ]);
    }

} catch (Exception $e) {
    error_log("Story details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch story details: ' . $e->getMessage()]);
}
?>
