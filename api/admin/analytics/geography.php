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

    // Top countries
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(country_code, 'Unknown') as country_code,
            COUNT(DISTINCT ip_hash) as unique_visitors,
            COUNT(DISTINCT session_id) as sessions,
            COUNT(*) as total_events
        FROM analytics_events
        WHERE created_at >= ?
        GROUP BY country_code
        ORDER BY unique_visitors DESC
        LIMIT 20
    ");
    $stmt->execute([$startDate]);
    $topCountries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top regions
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(region, 'Unknown') as region,
            COALESCE(country_code, 'Unknown') as country_code,
            COUNT(DISTINCT ip_hash) as unique_visitors,
            COUNT(DISTINCT session_id) as sessions
        FROM analytics_events
        WHERE created_at >= ?
        AND region IS NOT NULL
        AND region != ''
        GROUP BY region, country_code
        ORDER BY unique_visitors DESC
        LIMIT 20
    ");
    $stmt->execute([$startDate]);
    $topRegions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top cities
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(city, 'Unknown') as city,
            COALESCE(region, 'Unknown') as region,
            COALESCE(country_code, 'Unknown') as country_code,
            COUNT(DISTINCT ip_hash) as unique_visitors,
            COUNT(DISTINCT session_id) as sessions
        FROM analytics_events
        WHERE created_at >= ?
        AND city IS NOT NULL
        AND city != ''
        GROUP BY city, region, country_code
        ORDER BY unique_visitors DESC
        LIMIT 20
    ");
    $stmt->execute([$startDate]);
    $topCities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Geographic distribution over time (daily breakdown by country)
    $stmt = $pdo->prepare("
        SELECT
            DATE(created_at) as date,
            COALESCE(country_code, 'Unknown') as country_code,
            COUNT(DISTINCT ip_hash) as unique_visitors
        FROM analytics_events
        WHERE created_at >= ?
        GROUP BY DATE(created_at), country_code
        ORDER BY date ASC, unique_visitors DESC
    ");
    $stmt->execute([$startDate]);
    $dailyGeo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'top_countries' => $topCountries,
            'top_regions' => $topRegions,
            'top_cities' => $topCities,
            'daily_geographic' => $dailyGeo,
            'period_days' => $days
        ]
    ]);

} catch (Exception $e) {
    error_log("Geographic analytics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch geographic analytics', 'debug' => $e->getMessage()]);
}
?>
