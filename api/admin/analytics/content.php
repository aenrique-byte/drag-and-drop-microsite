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
    $type = isset($_GET['type']) ? $_GET['type'] : 'stories';
    $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    $data = [];
    
    switch ($type) {
        case 'stories':
            // Get story performance with chapter counts and reading depth
            $stmt = $pdo->prepare("
                SELECT 
                    s.slug,
                    s.title,
                    s.status,
                    COUNT(DISTINCT CASE WHEN ae.event_type = 'story_view' THEN ae.id END) as story_views,
                    COUNT(DISTINCT CASE WHEN ae.event_type = 'chapter_view' THEN ae.id END) as chapter_views,
                    COUNT(DISTINCT CASE WHEN ae.event_type = 'chapter_view' THEN ae.session_id END) as unique_readers,
                    AVG(CASE WHEN ae.event_type = 'chapter_depth' THEN ae.value_num END) as avg_reading_depth,
                    COUNT(DISTINCT c.id) as total_chapters
                FROM stories s
                LEFT JOIN analytics_events ae ON ae.parent_id = s.id AND ae.parent_type = 'story'
                    AND ae.created_at >= ?
                LEFT JOIN chapters c ON c.story_id = s.id
                WHERE s.status = 'published'
                GROUP BY s.id, s.slug, s.title, s.status
                ORDER BY (COUNT(DISTINCT CASE WHEN ae.event_type = 'story_view' THEN ae.id END) + 
                         COUNT(DISTINCT CASE WHEN ae.event_type = 'chapter_view' THEN ae.id END)) DESC
            ");
            $stmt->execute([$startDate]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format the data
            foreach ($data as &$row) {
                $row['story_views'] = (int)$row['story_views'];
                $row['chapter_views'] = (int)$row['chapter_views'];
                $row['unique_readers'] = (int)$row['unique_readers'];
                $row['total_chapters'] = (int)$row['total_chapters'];
                $row['avg_reading_depth'] = $row['avg_reading_depth'] ? round((float)$row['avg_reading_depth'], 1) : 0;
                $row['total_views'] = $row['story_views'] + $row['chapter_views'];
            }
            break;
            
        case 'chapters':
            // Get chapter performance with reading metrics
            $stmt = $pdo->prepare("
                SELECT 
                    c.id,
                    c.title,
                    c.chapter_number,
                    c.slug as chapter_slug,
                    s.title as story_title,
                    s.slug as story_slug,
                    COUNT(DISTINCT CASE WHEN ae.event_type = 'chapter_view' THEN ae.id END) as views,
                    COUNT(DISTINCT CASE WHEN ae.event_type = 'chapter_view' THEN ae.session_id END) as unique_readers,
                    AVG(CASE WHEN ae.event_type = 'chapter_depth' THEN ae.value_num END) as avg_reading_depth,
                    AVG(CASE WHEN ae.event_type = 'chapter_depth' THEN JSON_EXTRACT(ae.meta_json, '$.time_ms') END) as avg_time_spent
                FROM chapters c
                JOIN stories s ON s.id = c.story_id
                LEFT JOIN analytics_events ae ON ae.content_id = c.id AND ae.content_type = 'chapter'
                    AND ae.created_at >= ?
                WHERE s.status = 'published'
                GROUP BY c.id, c.title, c.chapter_number, c.slug, s.title, s.slug
                ORDER BY COUNT(DISTINCT CASE WHEN ae.event_type = 'chapter_view' THEN ae.id END) DESC
                LIMIT 50
            ");
            $stmt->execute([$startDate]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format the data
            foreach ($data as &$row) {
                $row['id'] = (int)$row['id'];
                $row['chapter_number'] = (int)$row['chapter_number'];
                $row['views'] = (int)$row['views'];
                $row['unique_readers'] = (int)$row['unique_readers'];
                $row['avg_reading_depth'] = $row['avg_reading_depth'] ? round((float)$row['avg_reading_depth'], 1) : 0;
                $row['avg_time_spent'] = $row['avg_time_spent'] ? round((float)$row['avg_time_spent'], 0) : 0;
            }
            break;
            
        case 'galleries':
            // Get gallery performance
            $stmt = $pdo->prepare("
                SELECT 
                    g.id,
                    g.title,
                    g.slug,
                    g.status,
                    COUNT(DISTINCT CASE WHEN ae.event_type = 'gallery_view' THEN ae.id END) as gallery_views,
                    COUNT(DISTINCT CASE WHEN ae.event_type = 'image_view' AND ae.parent_id = g.id THEN ae.id END) as image_views,
                    COUNT(DISTINCT CASE WHEN ae.event_type = 'gallery_view' THEN ae.session_id END) as unique_visitors,
                    COUNT(DISTINCT gi.id) as total_images
                FROM galleries g
                LEFT JOIN analytics_events ae ON (ae.content_id = g.id AND ae.content_type = 'gallery') OR (ae.parent_id = g.id AND ae.parent_type = 'gallery')
                    AND ae.created_at >= ?
                LEFT JOIN gallery_images gi ON gi.gallery_id = g.id
                WHERE g.status = 'published'
                GROUP BY g.id, g.title, g.slug, g.status
                ORDER BY (COUNT(DISTINCT CASE WHEN ae.event_type = 'gallery_view' THEN ae.id END) + 
                         COUNT(DISTINCT CASE WHEN ae.event_type = 'image_view' AND ae.parent_id = g.id THEN ae.id END)) DESC
            ");
            $stmt->execute([$startDate]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format the data
            foreach ($data as &$row) {
                $row['id'] = (int)$row['id'];
                $row['gallery_views'] = (int)$row['gallery_views'];
                $row['image_views'] = (int)$row['image_views'];
                $row['unique_visitors'] = (int)$row['unique_visitors'];
                $row['total_images'] = (int)$row['total_images'];
                $row['total_views'] = $row['gallery_views'] + $row['image_views'];
            }
            break;
            
        case 'pages':
            // Get page performance
            $stmt = $pdo->prepare("
                SELECT 
                    url_path as page_path,
                    JSON_EXTRACT(meta_json, '$.page_title') as page_title,
                    COUNT(*) as views,
                    COUNT(DISTINCT session_id) as unique_visitors,
                    AVG(JSON_EXTRACT(meta_json, '$.time_on_page')) as avg_time_on_page
                FROM analytics_events 
                WHERE event_type = 'page_view'
                AND created_at >= ?
                AND url_path IS NOT NULL
                GROUP BY url_path, JSON_EXTRACT(meta_json, '$.page_title')
                ORDER BY views DESC
                LIMIT 50
            ");
            $stmt->execute([$startDate]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format the data
            foreach ($data as &$row) {
                $row['page_title'] = $row['page_title'] ? trim($row['page_title'], '"') : 'Unknown';
                $row['views'] = (int)$row['views'];
                $row['unique_visitors'] = (int)$row['unique_visitors'];
                $row['avg_time_on_page'] = $row['avg_time_on_page'] ? round((float)$row['avg_time_on_page'], 0) : 0;
            }
            break;
            
        default:
            throw new Exception('Invalid content type');
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'content_type' => $type,
            'content' => $data,
            'period_days' => $days
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Analytics content error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch content data: ' . $e->getMessage()]);
}
?>
