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
    echo json_encode(['error' => 'Story ID is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Check if story exists
    $stmt = $pdo->prepare("SELECT id FROM stories WHERE id = ?");
    $stmt->execute([$input['id']]);
    if (!$stmt->fetch()) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Story not found']);
        exit;
    }

    // Delete all chapters first (due to foreign key constraint)
    $stmt = $pdo->prepare("DELETE FROM chapters WHERE story_id = ?");
    $stmt->execute([$input['id']]);

    // Delete the story
    $stmt = $pdo->prepare("DELETE FROM stories WHERE id = ?");
    $stmt->execute([$input['id']]);

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Story delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete story']);
}
?>
