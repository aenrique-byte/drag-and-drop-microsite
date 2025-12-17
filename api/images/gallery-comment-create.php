<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Rate limit: 20 comments per minute per IP (fail open)
requireRateLimit('gallery_comment_create', 20, 60);

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $imageId = isset($data['image_id']) ? (int)$data['image_id'] : 0;
    $authorName = isset($data['author_name']) ? trim($data['author_name']) : 'Anonymous';
    $content = isset($data['content']) ? trim($data['content']) : '';

    if ($imageId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'image_id is required']);
        exit;
    }

    if ($content === '') {
        http_response_code(400);
        echo json_encode(['error' => 'content is required']);
        exit;
    }

    // Basic limits - truncate instead of rejecting (like storytime)
    if (mb_strlen($authorName) > 100) {
        $authorName = mb_substr($authorName, 0, 100);
    }
    if (mb_strlen($content) > 1000) {
        $content = mb_substr($content, 0, 1000);
    }

    // Use immediate approval for now (frontend copy says comments appear immediately)
    $ip = getClientIP();
    $uah = getUserAgentHash();

    // Ensure image exists
    $stmt = $pdo->prepare("SELECT id FROM images WHERE id = ? LIMIT 1");
    $stmt->execute([$imageId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
        exit;
    }

    // Insert comment
    $stmt = $pdo->prepare("
        INSERT INTO image_comments (image_id, author_name, content, is_approved, ip_address, user_agent_hash)
        VALUES (?, ?, ?, 1, ?, ?)
    ");
    $stmt->execute([$imageId, $authorName, $content, $ip, $uah]);

    // Recompute approved comment count and sync on images table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM image_comments WHERE image_id = ? AND is_approved = 1");
    $stmt->execute([$imageId]);
    $count = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("UPDATE images SET comment_count = ? WHERE id = ?");
    $stmt->execute([$count, $imageId]);

    echo json_encode([
        'success' => true,
        'message' => 'Comment submitted successfully',
        'comment_count' => $count
    ]);
} catch (Exception $e) {
    error_log('Image comment create error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit comment']);
}
