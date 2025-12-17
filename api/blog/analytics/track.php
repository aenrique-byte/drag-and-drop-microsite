<?php
/**
 * Blog Analytics Tracking Endpoint
 * 
 * Wrapper around the main analytics/ingest.php for blog-specific tracking.
 * Tracks: blog_view, blog_like, blog_share events
 * 
 * Usage:
 *   POST /api/blog/analytics/track.php
 *   {
 *     "event_type": "blog_view" | "blog_like" | "blog_share",
 *     "post_id": 123,
 *     "post_slug": "my-blog-post"
 *   }
 */

require_once '../../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
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
if (!isset($input['event_type']) || !isset($input['post_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: event_type, post_id']);
    exit;
}

// Validate event_type
$allowed_events = ['blog_view', 'blog_like', 'blog_share'];
if (!in_array($input['event_type'], $allowed_events)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event_type. Must be: blog_view, blog_like, or blog_share']);
    exit;
}

$post_id = intval($input['post_id']);
$post_slug = $input['post_slug'] ?? '';

// Verify the blog post exists
try {
    $stmt = $pdo->prepare("SELECT id, slug, status FROM blog_posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        http_response_code(404);
        echo json_encode(['error' => 'Blog post not found']);
        exit;
    }
} catch (Exception $e) {
    error_log("Blog analytics track error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

// Get or generate session ID
$session_id = $input['session_id'] ?? null;
if (!$session_id) {
    // Try to get from cookie or generate new one
    if (isset($_COOKIE['analytics_session'])) {
        $session_id = $_COOKIE['analytics_session'];
    } else {
        $session_id = bin2hex(random_bytes(16));
        // Note: Cookie should be set on frontend
    }
}

// Get IP hash for deduplication
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip_hash = hash('sha256', $ip . ANALYTICS_SALT);

// For likes - check if already liked (24-hour window)
if ($input['event_type'] === 'blog_like') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM analytics_events 
        WHERE ip_hash = ? 
        AND event_type = 'blog_like' 
        AND content_type = 'blog_post'
        AND content_id = ?
        AND created_at > NOW() - INTERVAL 24 HOUR
    ");
    $stmt->execute([$ip_hash, $post_id]);
    
    if ($stmt->fetchColumn() > 0) {
        // Already liked, but return success (idempotent)
        echo json_encode(['success' => true, 'already_tracked' => true]);
        exit;
    }
}

// For views - check recent duplicate (5-minute window)
if ($input['event_type'] === 'blog_view') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM analytics_events 
        WHERE ip_hash = ? 
        AND event_type = 'blog_view' 
        AND content_type = 'blog_post'
        AND content_id = ?
        AND created_at > NOW() - INTERVAL 5 MINUTE
    ");
    $stmt->execute([$ip_hash, $post_id]);
    
    if ($stmt->fetchColumn() > 0) {
        // Recent view exists, skip tracking but return success
        echo json_encode(['success' => true, 'already_tracked' => true]);
        exit;
    }
}

// Forward to main ingest endpoint
$forward_data = [
    'event_type' => $input['event_type'],
    'session_id' => $session_id,
    'url_path' => '/blog/' . ($post_slug ?: $post['slug']),
    'content_type' => 'blog_post',
    'content_id' => $post_id,
    'referrer' => $_SERVER['HTTP_REFERER'] ?? null,
    'meta' => [
        'post_slug' => $post_slug ?: $post['slug'],
        'source' => $input['source'] ?? 'direct'
    ]
];

// Make internal request to ingest.php
$ingest_url = 'http://' . $_SERVER['HTTP_HOST'] . '/api/analytics/ingest.php';

$ch = curl_init($ingest_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($forward_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Internal'),
    'X-Forwarded-For: ' . $ip
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    // Fallback: insert directly
    try {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $pdo->prepare("
            INSERT INTO analytics_events (
                session_id, event_type, url_path, referrer, 
                user_agent, ip_hash, content_type, content_id, meta_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $session_id,
            $input['event_type'],
            '/blog/' . ($post_slug ?: $post['slug']),
            $_SERVER['HTTP_REFERER'] ?? null,
            substr($user_agent, 0, 255),
            $ip_hash,
            'blog_post',
            $post_id,
            json_encode($forward_data['meta'])
        ]);
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("Blog analytics direct insert error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to track event']);
    }
    exit;
}

$result = json_decode($response, true);

// If tracking was successful, update the blog_posts table counters
if ($result && isset($result['success']) && $result['success']) {
    try {
        if ($input['event_type'] === 'blog_view') {
            // Increment view count (will be synced by daily cron, but update for immediate feedback)
            $pdo->prepare("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?")->execute([$post_id]);
        } elseif ($input['event_type'] === 'blog_like') {
            // Increment like count
            $pdo->prepare("UPDATE blog_posts SET like_count = like_count + 1 WHERE id = ?")->execute([$post_id]);
        }
    } catch (Exception $e) {
        // Log but don't fail - the analytics event was recorded
        error_log("Blog counter update error: " . $e->getMessage());
    }
}

echo json_encode(['success' => true]);
