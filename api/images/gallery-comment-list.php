<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_GET['image_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'image_id is required']);
    exit;
}

$imageId = intval($_GET['image_id']);
if ($imageId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid image ID']);
    exit;
}

try {
    $pdo = db();
    
    // Verify image exists
    $stmt = $pdo->prepare("SELECT id FROM images WHERE id = ?");
    $stmt->execute([$imageId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Image not found']);
        exit;
    }

    // Get approved comments for this image
    $stmt = $pdo->prepare("
        SELECT id, author_name, content, created_at
        FROM image_comments
        WHERE image_id = ? AND is_approved = 1
        ORDER BY created_at ASC
    ");
    $stmt->execute([$imageId]);
    $comments = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
} catch (Exception $e) {
    error_log('Image comments list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load comments']);
}
