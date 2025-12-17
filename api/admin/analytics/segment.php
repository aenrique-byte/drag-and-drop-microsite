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
    $segment = isset($_GET['segment']) ? $_GET['segment'] : 'device';
    $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    $data = [];
    
    switch ($segment) {
        case 'device':
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(JSON_EXTRACT(meta_json, '$.device_type'), 'unknown') as segment,
                    COUNT(*) as views,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM analytics_events 
                WHERE event_type = 'page_view'
                AND created_at >= ?
                GROUP BY JSON_EXTRACT(meta_json, '$.device_type')
                ORDER BY views DESC
            ");
            break;
            
        case 'browser':
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(JSON_EXTRACT(meta_json, '$.browser'), 'unknown') as segment,
                    COUNT(*) as views,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM analytics_events 
                WHERE event_type = 'page_view'
                AND created_at >= ?
                GROUP BY JSON_EXTRACT(meta_json, '$.browser')
                ORDER BY views DESC
            ");
            break;
            
        case 'referrer':
            $stmt = $pdo->prepare("
                SELECT 
                    CASE 
                        WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                        WHEN referrer LIKE '%google%' THEN 'Google'
                        WHEN referrer LIKE '%bing%' THEN 'Bing'
                        WHEN referrer LIKE '%facebook%' THEN 'Facebook'
                        WHEN referrer LIKE '%twitter%' THEN 'Twitter'
                        WHEN referrer LIKE '%reddit%' THEN 'Reddit'
                        ELSE 'Other'
                    END as segment,
                    COUNT(*) as views,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM analytics_events 
                WHERE event_type = 'page_view'
                AND created_at >= ?
                GROUP BY segment
                ORDER BY views DESC
            ");
            break;
            
        case 'hour':
            $stmt = $pdo->prepare("
                SELECT 
                    HOUR(created_at) as segment,
                    COUNT(*) as views,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM analytics_events 
                WHERE event_type = 'page_view'
                AND created_at >= ?
                GROUP BY HOUR(created_at)
                ORDER BY segment
            ");
            break;
            
        case 'day_of_week':
            $stmt = $pdo->prepare("
                SELECT 
                    CASE DAYOFWEEK(created_at)
                        WHEN 1 THEN 'Sunday'
                        WHEN 2 THEN 'Monday'
                        WHEN 3 THEN 'Tuesday'
                        WHEN 4 THEN 'Wednesday'
                        WHEN 5 THEN 'Thursday'
                        WHEN 6 THEN 'Friday'
                        WHEN 7 THEN 'Saturday'
                    END as segment,
                    COUNT(*) as views,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM analytics_events 
                WHERE event_type = 'page_view'
                AND created_at >= ?
                GROUP BY DAYOFWEEK(created_at)
                ORDER BY DAYOFWEEK(created_at)
            ");
            break;
            
        case 'content_type':
            $stmt = $pdo->prepare("
                SELECT 
                    event_type as segment,
                    COUNT(*) as views,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM analytics_events 
                WHERE event_type IN ('story_view', 'chapter_view', 'gallery_view', 'image_view')
                AND created_at >= ?
                GROUP BY event_type
                ORDER BY views DESC
            ");
            break;
            
        default:
            throw new Exception('Invalid segment type');
    }
    
    $stmt->execute([$startDate]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert numeric segments to strings for consistency
    foreach ($data as &$row) {
        $row['segment'] = (string)$row['segment'];
        $row['views'] = (int)$row['views'];
        $row['unique_visitors'] = (int)$row['unique_visitors'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'segment_type' => $segment,
            'segments' => $data,
            'period_days' => $days
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Analytics segment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch segment data: ' . $e->getMessage()]);
}
?>
