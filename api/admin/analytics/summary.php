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
    
    // Get date range from query params (default to last 30 days)
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    // Total page views
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_views 
        FROM analytics_events 
        WHERE event_type = 'page_view' 
        AND created_at >= ?
    ");
    $stmt->execute([$startDate]);
    $totalViews = $stmt->fetchColumn();
    
    // Unique visitors (based on session_id)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT session_id) as unique_visitors 
        FROM analytics_events 
        WHERE created_at >= ?
    ");
    $stmt->execute([$startDate]);
    $uniqueVisitors = $stmt->fetchColumn();
    
    // Story views
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as story_views 
        FROM analytics_events 
        WHERE event_type = 'story_view' 
        AND created_at >= ?
    ");
    $stmt->execute([$startDate]);
    $storyViews = $stmt->fetchColumn();
    
    // Chapter views
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as chapter_views 
        FROM analytics_events 
        WHERE event_type = 'chapter_view' 
        AND created_at >= ?
    ");
    $stmt->execute([$startDate]);
    $chapterViews = $stmt->fetchColumn();
    
    // Gallery views
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as gallery_views 
        FROM analytics_events 
        WHERE event_type = 'gallery_view' 
        AND created_at >= ?
    ");
    $stmt->execute([$startDate]);
    $galleryViews = $stmt->fetchColumn();
    
    // Average reading depth for chapters (using value_num column)
    $stmt = $pdo->prepare("
        SELECT AVG(value_num) as avg_depth
        FROM analytics_events 
        WHERE event_type = 'chapter_depth' 
        AND created_at >= ?
        AND value_num IS NOT NULL
    ");
    $stmt->execute([$startDate]);
    $avgDepth = $stmt->fetchColumn() ?: 0;
    
    // Top stories by views (using parent_id to get story data)
    $stmt = $pdo->prepare("
        SELECT 
            s.slug as story_slug,
            COUNT(*) as views
        FROM analytics_events ae
        JOIN stories s ON s.id = ae.parent_id
        WHERE ae.event_type IN ('story_view', 'chapter_view')
        AND ae.created_at >= ?
        AND ae.parent_type = 'story'
        AND ae.parent_id IS NOT NULL
        GROUP BY s.slug, s.id
        ORDER BY views DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate]);
    $topStories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily views trend
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as views
        FROM analytics_events 
        WHERE event_type = 'page_view'
        AND created_at >= ?
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    $stmt->execute([$startDate]);
    $dailyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'summary' => [
                'total_views' => (int)$totalViews,
                'unique_visitors' => (int)$uniqueVisitors,
                'story_views' => (int)$storyViews,
                'chapter_views' => (int)$chapterViews,
                'gallery_views' => (int)$galleryViews,
                'avg_reading_depth' => round((float)$avgDepth, 1)
            ],
            'top_stories' => $topStories,
            'daily_trend' => array_reverse($dailyTrend), // Show oldest to newest
            'period_days' => $days
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Analytics summary error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch analytics data', 'debug' => $e->getMessage()]);
}
?>
