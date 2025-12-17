<?php
require_once '../../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Rate limit: 5 comments per minute per IP (fail open)
requireRateLimit('comment_create', 5, 60);

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];

    $chapterId = isset($data['chapter_id']) ? (int)$data['chapter_id'] : 0;
    $authorName = isset($data['author_name']) ? trim($data['author_name']) : 'Anonymous';
    $content = isset($data['content']) ? trim($data['content']) : '';

    if ($chapterId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'chapter_id is required']);
        exit;
    }

    if ($content === '') {
        http_response_code(400);
        echo json_encode(['error' => 'content is required']);
        exit;
    }

    // Basic limits
    if (mb_strlen($authorName) > 100) {
        $authorName = mb_substr($authorName, 0, 100);
    }
    if (mb_strlen($content) > 1000) {
        $content = mb_substr($content, 0, 1000);
    }

    // Use immediate approval for now (frontend copy says comments appear immediately)
    $ip = getClientIP();
    $uah = getUserAgentHash();

    // Ensure chapter exists
    $stmt = $pdo->prepare("SELECT id FROM chapters WHERE id = ? LIMIT 1");
    $stmt->execute([$chapterId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => 'Chapter not found']);
        exit;
    }

    // Insert comment
    $stmt = $pdo->prepare("
        INSERT INTO chapter_comments (chapter_id, author_name, content, is_approved, ip_address, user_agent_hash)
        VALUES (?, ?, ?, 1, ?, ?)
    ");
    $stmt->execute([$chapterId, $authorName, $content, $ip, $uah]);

    // Recompute approved comment count and sync on chapters table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chapter_comments WHERE chapter_id = ? AND is_approved = 1");
    $stmt->execute([$chapterId]);
    $count = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("UPDATE chapters SET comment_count = ? WHERE id = ?");
    $stmt->execute([$count, $chapterId]);

    echo json_encode([
        'success' => true,
        'message' => 'Comment submitted successfully',
        'comment_count' => $count
    ]);
} catch (Exception $e) {
    error_log('Chapter comment create error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to submit comment']);
}
