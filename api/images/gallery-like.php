<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

function getLikeStatus(PDO $pdo, int $imageId): array {
    // Count total likes
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM image_likes WHERE image_id = ?");
    $stmt->execute([$imageId]);
    $count = (int)$stmt->fetchColumn();

    // Determine if this client already liked
    $ip = getClientIP();
    $uah = getUserAgentHash();
    $stmt = $pdo->prepare("SELECT 1 FROM image_likes WHERE image_id = ? AND ip_address = ? AND user_agent_hash = ? LIMIT 1");
    $stmt->execute([$imageId, $ip, $uah]);
    $userLiked = (bool)$stmt->fetchColumn();

    return ['like_count' => $count, 'user_liked' => $userLiked];
}


try {
    $pdo = db();
    
    if ($method === 'GET') {
        if (!isset($_GET['image_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'image_id is required']);
            exit;
        }
        $imageId = (int)$_GET['image_id'];
        $status = getLikeStatus($pdo, $imageId);
        echo json_encode(['success' => true] + $status);
        exit;
    } elseif ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: [];
        if (!isset($data['image_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'image_id is required']);
            exit;
        }
        $imageId = (int)$data['image_id'];

        $ip = getClientIP();
        $uah = getUserAgentHash();

        // Try to insert like (dedup by unique key)
        $stmt = $pdo->prepare("
            INSERT INTO image_likes (image_id, ip_address, user_agent_hash)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE image_id = image_id
        ");
        $stmt->execute([$imageId, $ip, $uah]);

        // Recompute count and whether this user liked
        $status = getLikeStatus($pdo, $imageId);

        // Sync cached like_count on images table
        $stmt = $pdo->prepare("UPDATE images SET like_count = ? WHERE id = ?");
        $stmt->execute([$status['like_count'], $imageId]);

        echo json_encode(['success' => true] + $status);
        exit;
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }
} catch (Exception $e) {
    error_log("Image like error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process like']);
    exit;
}
