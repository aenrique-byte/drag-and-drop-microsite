<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Same-origin check
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';

$allowed_hosts = [$host, 'localhost:3000']; // Add your domain here
$origin_host = $origin ? parse_url($origin, PHP_URL_HOST) : null;
$referer_host = $referer ? parse_url($referer, PHP_URL_HOST) : null;

if ($origin_host && !in_array($origin_host, $allowed_hosts)) {
    http_response_code(403);
    echo json_encode(['error' => 'Cross-origin requests not allowed']);
    exit;
}

// Bot filter
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/bot|spider|crawl|curl|wget|headless|facebookexternalhit|slurp|bing|yandex/i', $user_agent)) {
    http_response_code(200);
    echo json_encode(['success' => true, 'filtered' => 'bot']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$required_fields = ['event_type', 'session_id', 'url_path'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Validate event_type
$allowed_events = ['page_view', 'story_view', 'gallery_view', 'image_view', 'chapter_view', 'chapter_depth', 'click', 'blog_view', 'blog_like', 'blog_share'];
if (!in_array($input['event_type'], $allowed_events)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event_type']);
    exit;
}

// Validate content_type if provided
if (isset($input['content_type'])) {
    $allowed_content_types = ['site', 'story', 'gallery', 'chapter', 'image', 'blog', 'blog_post'];
    if (!in_array($input['content_type'], $allowed_content_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid content_type']);
        exit;
    }
}

// Validate parent_type if provided
if (isset($input['parent_type'])) {
    $allowed_parent_types = ['story', 'gallery'];
    if (!in_array($input['parent_type'], $allowed_parent_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid parent_type']);
        exit;
    }
}

try {
    // Get IP and create hash
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $analytics_salt = ANALYTICS_SALT;
    $ip_hash = hash('sha256', $ip . $analytics_salt);

    // Get user_id from session if logged in (session already started in bootstrap.php)
    $user_id = $_SESSION['user_id'] ?? null;

    // Geolocation lookup using ip-api.com (free tier: 45 requests/minute)
    // Cache results to avoid repeated lookups for same IP
    $country_code = null;
    $region = null;
    $city = null;

    // Check if we have cached geo data for this IP (within last 24 hours)
    $cache_key = 'geo_' . $ip_hash;
    $stmt = $pdo->prepare("
        SELECT country_code, region, city
        FROM analytics_events
        WHERE ip_hash = ?
        AND country_code IS NOT NULL
        AND created_at > NOW() - INTERVAL 24 HOUR
        LIMIT 1
    ");
    $stmt->execute([$ip_hash]);
    $cached_geo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cached_geo) {
        // Use cached geolocation data
        $country_code = $cached_geo['country_code'];
        $region = $cached_geo['region'];
        $city = $cached_geo['city'];
    } else {
        // Perform geolocation lookup for new IP
        // Skip lookup for localhost/private IPs
        if (!in_array($ip, ['127.0.0.1', '::1', 'localhost']) &&
            !preg_match('/^(10|172\.(1[6-9]|2[0-9]|3[01])|192\.168)\./', $ip)) {

            try {
                $geo_url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,region,regionName,city";
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 2, // 2 second timeout
                        'ignore_errors' => true
                    ]
                ]);

                $geo_response = @file_get_contents($geo_url, false, $context);

                if ($geo_response) {
                    $geo_data = json_decode($geo_response, true);

                    if ($geo_data && isset($geo_data['status']) && $geo_data['status'] === 'success') {
                        $country_code = $geo_data['countryCode'] ?? null;
                        $region = $geo_data['regionName'] ?? null;
                        $city = $geo_data['city'] ?? null;
                    }
                }
            } catch (Exception $e) {
                // Silently fail - geo lookup is optional
                error_log("Geo lookup error for IP {$ip}: " . $e->getMessage());
            }
        }
    }

    // Prepare data for insertion
    $data = [
        'session_id' => substr($input['session_id'], 0, 36), // Ensure max length
        'user_id' => $user_id,
        'event_type' => $input['event_type'],
        'url_path' => substr($input['url_path'], 0, 512),
        'referrer' => isset($input['referrer']) ? substr($input['referrer'], 0, 512) : null,
        'user_agent' => substr($user_agent, 0, 255),
        'ip_hash' => $ip_hash,
        'country_code' => $country_code,
        'region' => $region,
        'city' => $city,
        'content_type' => $input['content_type'] ?? null,
        'content_id' => isset($input['content_id']) ? intval($input['content_id']) : null,
        'parent_type' => $input['parent_type'] ?? null,
        'parent_id' => isset($input['parent_id']) ? intval($input['parent_id']) : null,
        'value_num' => isset($input['value_num']) ? floatval($input['value_num']) : null,
        'meta_json' => isset($input['meta']) ? json_encode($input['meta']) : null
    ];

    // Simple throttle check (optional)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM analytics_events 
        WHERE session_id = ? AND created_at > NOW() - INTERVAL 1 SECOND
    ");
    $stmt->execute([$data['session_id']]);
    $recent_count = $stmt->fetchColumn();

    if ($recent_count > 5) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded']);
        exit;
    }

    // Insert event
    $stmt = $pdo->prepare("
        INSERT INTO analytics_events (
            session_id, user_id, event_type, url_path, referrer, 
            user_agent, ip_hash, country_code, region, city,
            content_type, content_id, parent_type, parent_id, 
            value_num, meta_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['session_id'],
        $data['user_id'],
        $data['event_type'],
        $data['url_path'],
        $data['referrer'],
        $data['user_agent'],
        $data['ip_hash'],
        $data['country_code'],
        $data['region'],
        $data['city'],
        $data['content_type'],
        $data['content_id'],
        $data['parent_type'],
        $data['parent_id'],
        $data['value_num'],
        $data['meta_json']
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Analytics ingest error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to record event']);
}
?>
