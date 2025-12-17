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
    $gallery_id = isset($_GET['gallery_id']) ? (int)$_GET['gallery_id'] : null;
    $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

    if (!$gallery_id) {
        // Return list of all published galleries with basic analytics
        $stmt = $pdo->prepare("
            SELECT
                g.id,
                g.slug,
                g.title,
                g.status,
                g.rating,
                COUNT(DISTINCT gi.id) as total_images,
                g.like_count as total_likes,
                g.comment_count as total_comments,
                COUNT(DISTINCT CASE WHEN ae.event_type = 'gallery_view' THEN ae.id END) as gallery_views,
                COUNT(DISTINCT CASE WHEN ae.event_type = 'image_view' AND ae.parent_id = g.id THEN ae.id END) as image_views,
                COUNT(DISTINCT CASE WHEN ae.event_type = 'gallery_view' THEN ae.session_id END) as unique_visitors
            FROM galleries g
            LEFT JOIN images gi ON gi.gallery_id = g.id
            LEFT JOIN analytics_events ae ON (ae.content_id = g.id AND ae.content_type = 'gallery')
                OR (ae.parent_id = g.id AND ae.parent_type = 'gallery')
                AND ae.created_at >= ?
            WHERE g.status = 'published'
            GROUP BY g.id, g.slug, g.title, g.status, g.rating, g.like_count, g.comment_count
            ORDER BY g.title ASC
        ");
        $stmt->execute([$startDate]);
        $galleries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data
        foreach ($galleries as &$gallery) {
            $gallery['id'] = (int)$gallery['id'];
            $gallery['total_images'] = (int)$gallery['total_images'];
            $gallery['total_likes'] = (int)$gallery['total_likes'];
            $gallery['total_comments'] = (int)$gallery['total_comments'];
            $gallery['gallery_views'] = (int)$gallery['gallery_views'];
            $gallery['image_views'] = (int)$gallery['image_views'];
            $gallery['unique_visitors'] = (int)$gallery['unique_visitors'];
            $gallery['total_views'] = $gallery['gallery_views'] + $gallery['image_views'];
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'galleries' => $galleries,
                'period_days' => $days
            ]
        ]);
    } else {
        // Return detailed image analytics for specific gallery
        $stmt = $pdo->prepare("
            SELECT
                i.id,
                i.title,
                i.filename,
                i.sort_order,
                i.like_count as likes,
                i.comment_count as comments,
                COUNT(DISTINCT CASE WHEN ae.event_type = 'image_view' THEN ae.id END) as views,
                COUNT(DISTINCT CASE WHEN ae.event_type = 'image_view' THEN ae.session_id END) as unique_viewers
            FROM images i
            LEFT JOIN analytics_events ae ON ae.content_id = i.id AND ae.content_type = 'image'
                AND ae.created_at >= ?
            WHERE i.gallery_id = ?
            GROUP BY i.id, i.title, i.filename, i.sort_order, i.like_count, i.comment_count
            ORDER BY i.sort_order ASC
        ");
        $stmt->execute([$startDate, $gallery_id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data
        foreach ($images as &$image) {
            $image['id'] = (int)$image['id'];
            $image['sort_order'] = (int)$image['sort_order'];
            $image['likes'] = (int)$image['likes'];
            $image['comments'] = (int)$image['comments'];
            $image['views'] = (int)$image['views'];
            $image['unique_viewers'] = (int)$image['unique_viewers'];
        }

        // Get gallery info
        $stmt = $pdo->prepare("SELECT id, slug, title, rating FROM galleries WHERE id = ?");
        $stmt->execute([$gallery_id]);
        $gallery = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'gallery' => $gallery,
                'images' => $images,
                'period_days' => $days
            ]
        ]);
    }

} catch (Exception $e) {
    error_log("Gallery details error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch gallery details: ' . $e->getMessage()]);
}
?>
