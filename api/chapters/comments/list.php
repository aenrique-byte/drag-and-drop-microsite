<?php
require_once '../../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    if (!isset($_GET['chapter_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'chapter_id is required']);
        exit;
    }

    $chapterId = (int)$_GET['chapter_id'];

    $stmt = $pdo->prepare("
        SELECT id, author_name, content AS comment_text, created_at
        FROM chapter_comments
        WHERE chapter_id = ? AND is_approved = 1
        ORDER BY created_at ASC
    ");
    $stmt->execute([$chapterId]);
    $comments = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
} catch (Exception $e) {
    error_log('Chapter comments list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load comments']);
}
