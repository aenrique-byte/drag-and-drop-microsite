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

    // Get date range from query params (default to last 7 days for activity feed)
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

    // Recent visitor sessions with geographic info
    $stmt = $pdo->prepare("
        SELECT
            ae.session_id,
            ae.ip_hash,
            MIN(ae.created_at) as first_seen,
            MAX(ae.created_at) as last_seen,
            TIMESTAMPDIFF(SECOND, MIN(ae.created_at), MAX(ae.created_at)) as duration_seconds,
            COUNT(DISTINCT CASE WHEN ae.event_type = 'page_view' THEN ae.id END) as page_views,
            COALESCE(ae.country_code, 'Unknown') as country_code,
            ae.region,
            ae.city,
            ae.user_agent,
            GROUP_CONCAT(DISTINCT ae.event_type ORDER BY ae.created_at SEPARATOR ',') as event_types
        FROM analytics_events ae
        WHERE ae.created_at >= ?
        GROUP BY ae.session_id, ae.ip_hash, ae.country_code, ae.region, ae.city, ae.user_agent
        ORDER BY first_seen DESC
        LIMIT ?
    ");
    $stmt->execute([$startDate, $limit]);
    $recentSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Activity by hour of day
    $stmt = $pdo->prepare("
        SELECT
            HOUR(created_at) as hour,
            COUNT(DISTINCT session_id) as sessions,
            COUNT(DISTINCT ip_hash) as unique_visitors,
            COUNT(*) as events
        FROM analytics_events
        WHERE created_at >= ?
        GROUP BY HOUR(created_at)
        ORDER BY hour ASC
    ");
    $stmt->execute([$startDate]);
    $hourlyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Activity by day of week
    $stmt = $pdo->prepare("
        SELECT
            DAYOFWEEK(created_at) as day_of_week,
            DAYNAME(created_at) as day_name,
            COUNT(DISTINCT session_id) as sessions,
            COUNT(DISTINCT ip_hash) as unique_visitors,
            COUNT(*) as events
        FROM analytics_events
        WHERE created_at >= ?
        GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at)
        ORDER BY day_of_week ASC
    ");
    $stmt->execute([$startDate]);
    $weeklyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Device/Browser breakdown (from user_agent)
    $stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN user_agent LIKE '%Mobile%' OR user_agent LIKE '%Android%' OR user_agent LIKE '%iPhone%' THEN 'Mobile'
                WHEN user_agent LIKE '%Tablet%' OR user_agent LIKE '%iPad%' THEN 'Tablet'
                ELSE 'Desktop'
            END as device_type,
            COUNT(DISTINCT ip_hash) as unique_visitors,
            COUNT(DISTINCT session_id) as sessions
        FROM analytics_events
        WHERE created_at >= ?
        AND user_agent IS NOT NULL
        GROUP BY device_type
        ORDER BY unique_visitors DESC
    ");
    $stmt->execute([$startDate]);
    $deviceBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Browser breakdown (simplified)
    $stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN user_agent LIKE '%Chrome%' AND user_agent NOT LIKE '%Edg%' THEN 'Chrome'
                WHEN user_agent LIKE '%Safari%' AND user_agent NOT LIKE '%Chrome%' THEN 'Safari'
                WHEN user_agent LIKE '%Firefox%' THEN 'Firefox'
                WHEN user_agent LIKE '%Edg%' THEN 'Edge'
                WHEN user_agent LIKE '%Opera%' OR user_agent LIKE '%OPR%' THEN 'Opera'
                ELSE 'Other'
            END as browser,
            COUNT(DISTINCT ip_hash) as unique_visitors,
            COUNT(DISTINCT session_id) as sessions
        FROM analytics_events
        WHERE created_at >= ?
        AND user_agent IS NOT NULL
        GROUP BY browser
        ORDER BY unique_visitors DESC
    ");
    $stmt->execute([$startDate]);
    $browserBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Referrer breakdown (top traffic sources)
    $stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN referrer_host IS NULL OR referrer_host = '' THEN 'Direct'
                ELSE referrer_host
            END as referrer,
            COUNT(DISTINCT session_id) as sessions,
            COUNT(DISTINCT ip_hash) as unique_visitors
        FROM analytics_events
        WHERE created_at >= ?
        AND event_type = 'page_view'
        GROUP BY referrer
        ORDER BY sessions DESC
        LIMIT 20
    ");
    $stmt->execute([$startDate]);
    $referrerBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'recent_sessions' => $recentSessions,
            'hourly_activity' => $hourlyActivity,
            'weekly_activity' => $weeklyActivity,
            'device_breakdown' => $deviceBreakdown,
            'browser_breakdown' => $browserBreakdown,
            'referrer_breakdown' => $referrerBreakdown,
            'period_days' => $days
        ]
    ]);

} catch (Exception $e) {
    error_log("Activity analytics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch activity analytics', 'debug' => $e->getMessage()]);
}
?>
