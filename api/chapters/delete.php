<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check authentication (session already started in bootstrap.php)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Chapter ID is required']);
    exit;
}

try {
    // Check if chapter exists and get story_id
    $stmt = $pdo->prepare("SELECT story_id FROM chapters WHERE id = ?");
    $stmt->execute([$input['id']]);
    $chapter = $stmt->fetch();
    if (!$chapter) {
        http_response_code(404);
        echo json_encode(['error' => 'Chapter not found']);
        exit;
    }

    // Delete the chapter
    $stmt = $pdo->prepare("DELETE FROM chapters WHERE id = ?");
    $stmt->execute([$input['id']]);

    // Update story's updated_at timestamp
    $stmt = $pdo->prepare("UPDATE stories SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$chapter['story_id']]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Chapter delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete chapter']);
}
?>
