<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

function getLikeStatus(PDO $pdo, int $chapterId): array {
    // Count total likes
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM chapter_likes WHERE chapter_id = ?");
    $stmt->execute([$chapterId]);
    $count = (int)$stmt->fetchColumn();

    // Determine if this client already liked
    $ip = getClientIP();
    $uah = getUserAgentHash();
    $stmt = $pdo->prepare("SELECT 1 FROM chapter_likes WHERE chapter_id = ? AND ip_address = ? AND user_agent_hash = ? LIMIT 1");
    $stmt->execute([$chapterId, $ip, $uah]);
    $userLiked = (bool)$stmt->fetchColumn();

    return ['like_count' => $count, 'user_liked' => $userLiked];
}

try {
    $pdo = db();
    
    if ($method === 'GET') {
        if (!isset($_GET['chapter_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'chapter_id is required']);
            exit;
        }
        $chapterId = (int)$_GET['chapter_id'];
        $status = getLikeStatus($pdo, $chapterId);
        echo json_encode(['success' => true] + $status);
        exit;
    } elseif ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];
        if (!isset($data['chapter_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'chapter_id is required']);
            exit;
        }
        $chapterId = (int)$data['chapter_id'];

        $ip = getClientIP();
        $uah = getUserAgentHash();

        // Try to insert like (dedup by unique key)
        $stmt = $pdo->prepare("
            INSERT INTO chapter_likes (chapter_id, ip_address, user_agent_hash)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE chapter_id = chapter_id
        ");
        $stmt->execute([$chapterId, $ip, $uah]);

        // Recompute count and whether this user liked
        $status = getLikeStatus($pdo, $chapterId);

        // Sync cached like_count on chapters table
        $stmt = $pdo->prepare("UPDATE chapters SET like_count = ? WHERE id = ?");
        $stmt->execute([$status['like_count'], $chapterId]);

        echo json_encode(['success' => true] + $status);
        exit;
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
} catch (Exception $e) {
    error_log("Chapter like error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process like']);
    exit;
}
