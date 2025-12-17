<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

function getLikeStatus(PDO $pdo, int $postId): array {
    // Count total likes
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM blog_likes WHERE post_id = ?");
    $stmt->execute([$postId]);
    $count = (int)$stmt->fetchColumn();

    // Determine if this client already liked
    $ip = getClientIP();
    $uah = getUserAgentHash();
    $stmt = $pdo->prepare("SELECT 1 FROM blog_likes WHERE post_id = ? AND ip_address = ? AND user_agent_hash = ? LIMIT 1");
    $stmt->execute([$postId, $ip, $uah]);
    $userLiked = (bool)$stmt->fetchColumn();

    return ['like_count' => $count, 'user_liked' => $userLiked];
}

try {
    $pdo = db();

    if ($method === 'GET') {
        if (!isset($_GET['post_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'post_id is required']);
            exit;
        }
        $postId = (int)$_GET['post_id'];
        $status = getLikeStatus($pdo, $postId);
        echo json_encode(['success' => true] + $status);
        exit;
    } elseif ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];
        if (!isset($data['post_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'post_id is required']);
            exit;
        }
        $postId = (int)$data['post_id'];

        $ip = getClientIP();
        $uah = getUserAgentHash();

        // Try to insert like (dedup by unique key)
        $stmt = $pdo->prepare("
            INSERT INTO blog_likes (post_id, ip_address, user_agent_hash)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE post_id = post_id
        ");
        $stmt->execute([$postId, $ip, $uah]);

        // Recompute count and whether this user liked
        $status = getLikeStatus($pdo, $postId);

        // Sync cached like_count on blog_posts table
        $stmt = $pdo->prepare("UPDATE blog_posts SET like_count = ? WHERE id = ?");
        $stmt->execute([$status['like_count'], $postId]);

        // Also track in analytics for reporting
        try {
            $session_id = $_COOKIE['analytics_session'] ?? bin2hex(random_bytes(16));
            $ip_hash = hash('sha256', $ip . (defined('ANALYTICS_SALT') ? ANALYTICS_SALT : 'default_salt'));

            // Check if already tracked in last 24 hours
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM analytics_events
                WHERE ip_hash = ?
                AND event_type = 'blog_like'
                AND content_type = 'blog_post'
                AND content_id = ?
                AND created_at > NOW() - INTERVAL 24 HOUR
            ");
            $checkStmt->execute([$ip_hash, $postId]);

            if ($checkStmt->fetchColumn() == 0) {
                // Insert analytics event
                $analyticsStmt = $pdo->prepare("
                    INSERT INTO analytics_events (
                        session_id, event_type, url_path, referrer,
                        user_agent, ip_hash, content_type, content_id, meta_json
                    ) VALUES (?, 'blog_like', ?, ?, ?, ?, 'blog_post', ?, ?)
                ");

                $post = $pdo->prepare("SELECT slug FROM blog_posts WHERE id = ?")->execute([$postId]);
                $slug = $pdo->query("SELECT slug FROM blog_posts WHERE id = {$postId}")->fetchColumn();

                $analyticsStmt->execute([
                    $session_id,
                    '/blog/' . $slug,
                    $_SERVER['HTTP_REFERER'] ?? null,
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                    $ip_hash,
                    $postId,
                    json_encode(['source' => 'direct'])
                ]);
            }
        } catch (Exception $analyticsError) {
            // Don't fail the like if analytics fails
            error_log("Blog like analytics tracking failed: " . $analyticsError->getMessage());
        }

        echo json_encode(['success' => true] + $status);
        exit;
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
} catch (Exception $e) {
    error_log("Blog like error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process like']);
    exit;
}
