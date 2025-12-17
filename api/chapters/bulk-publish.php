<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Require admin auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST; // allow form POST fallback
    }

    $storyId = isset($input['story_id']) ? intval($input['story_id']) : 0;
    $dryRun  = isset($input['dry_run']) ? (bool)$input['dry_run'] : false;

    if ($storyId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'story_id is required']);
        exit;
    }

    // Ensure story exists
    $stmt = $pdo->prepare("SELECT id, title FROM stories WHERE id = ?");
    $stmt->execute([$storyId]);
    $story = $stmt->fetch();
    if (!$story) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Story not found']);
        exit;
    }

    if ($dryRun) {
        // Count how many chapters would be affected (anything not already published)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chapters WHERE story_id = ? AND status <> 'published'");
        $stmt->execute([$storyId]);
        $wouldAffect = (int)$stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'dry_run' => true,
            'story_id' => $storyId,
            'would_affect' => $wouldAffect,
            'story_title' => $story['title']
        ]);
        exit;
    }

    // Publish all non-published chapters for this story
    $stmt = $pdo->prepare("
        UPDATE chapters 
        SET status = 'published',
            updated_at = NOW(),
            publish_at = IF(publish_at IS NULL, NOW(), publish_at)
        WHERE story_id = ? AND status <> 'published'
    ");
    $stmt->execute([$storyId]);
    $affected = $stmt->rowCount();

    // Touch story updated_at
    $stmt = $pdo->prepare("UPDATE stories SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$storyId]);

    echo json_encode([
        'success' => true,
        'story_id' => $storyId,
        'story_title' => $story['title'],
        'affected' => $affected
    ]);
    exit;

} catch (Exception $e) {
    error_log("Bulk publish error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to bulk publish chapters']);
    exit;
}
