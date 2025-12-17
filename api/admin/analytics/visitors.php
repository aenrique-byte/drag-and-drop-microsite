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

    // Total unique visitors (by IP hash)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ip_hash) as unique_ips
        FROM analytics_events
        WHERE created_at >= ?
    ");
    $stmt->execute([$startDate]);
    $uniqueIPs = $stmt->fetchColumn();

    // Total unique sessions
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT session_id) as unique_sessions
        FROM analytics_events
        WHERE created_at >= ?
    ");
    $stmt->execute([$startDate]);
    $uniqueSessions = $stmt->fetchColumn();

    // New vs Returning visitors
    // A visitor is "returning" if they have events before the start date
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN is_new = 1 THEN 1 ELSE 0 END) as new_visitors,
            SUM(CASE WHEN is_new = 0 THEN 1 ELSE 0 END) as returning_visitors
        FROM (
            SELECT
                ip_hash,
                CASE
                    WHEN MIN(created_at) >= ? THEN 1
                    ELSE 0
                END as is_new
            FROM analytics_events
            WHERE created_at >= ?
            GROUP BY ip_hash
        ) visitor_classification
    ");
    $stmt->execute([$startDate, $startDate]);
    $visitorTypes = $stmt->fetch(PDO::FETCH_ASSOC);

    // Repeat visitor rate (IPs with multiple sessions)
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT CASE WHEN session_count > 1 THEN ip_hash END) as repeat_visitors,
            COUNT(DISTINCT ip_hash) as total_visitors
        FROM (
            SELECT ip_hash, COUNT(DISTINCT session_id) as session_count
            FROM analytics_events
            WHERE created_at >= ?
            GROUP BY ip_hash
        ) session_counts
    ");
    $stmt->execute([$startDate]);
    $repeatData = $stmt->fetch(PDO::FETCH_ASSOC);
    $repeatVisitorRate = $repeatData['total_visitors'] > 0
        ? round(($repeatData['repeat_visitors'] / $repeatData['total_visitors']) * 100, 1)
        : 0;

    // Daily unique visitors trend
    $stmt = $pdo->prepare("
        SELECT
            DATE(created_at) as date,
            COUNT(DISTINCT ip_hash) as unique_visitors,
            COUNT(DISTINCT session_id) as sessions
        FROM analytics_events
        WHERE created_at >= ?
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute([$startDate]);
    $dailyVisitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Average session duration (time between first and last event per session)
    $stmt = $pdo->prepare("
        SELECT AVG(duration_seconds) as avg_duration
        FROM (
            SELECT
                session_id,
                TIMESTAMPDIFF(SECOND, MIN(created_at), MAX(created_at)) as duration_seconds
            FROM analytics_events
            WHERE created_at >= ?
            GROUP BY session_id
            HAVING COUNT(*) > 1
        ) session_durations
    ");
    $stmt->execute([$startDate]);
    $avgDuration = $stmt->fetchColumn() ?: 0;

    // Average pages per session
    $stmt = $pdo->prepare("
        SELECT AVG(page_count) as avg_pages
        FROM (
            SELECT session_id, COUNT(*) as page_count
            FROM analytics_events
            WHERE created_at >= ?
            AND event_type = 'page_view'
            GROUP BY session_id
        ) session_pages
    ");
    $stmt->execute([$startDate]);
    $avgPages = $stmt->fetchColumn() ?: 0;

    echo json_encode([
        'success' => true,
        'data' => [
            'unique_visitors' => (int)$uniqueIPs,
            'total_sessions' => (int)$uniqueSessions,
            'new_visitors' => (int)($visitorTypes['new_visitors'] ?? 0),
            'returning_visitors' => (int)($visitorTypes['returning_visitors'] ?? 0),
            'repeat_visitors' => (int)$repeatData['repeat_visitors'],
            'repeat_visitor_rate' => (float)$repeatVisitorRate,
            'avg_session_duration' => round((float)$avgDuration, 0),
            'avg_pages_per_session' => round((float)$avgPages, 1),
            'daily_visitors' => $dailyVisitors,
            'period_days' => $days
        ]
    ]);

} catch (Exception $e) {
    error_log("Visitor analytics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch visitor analytics', 'debug' => $e->getMessage()]);
}
?>
