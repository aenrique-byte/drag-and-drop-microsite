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

/**
 * Compute approximate word count from chapter content.
 * - No markdown/HTML processing; just whitespace-normalized token count.
 */
function compute_word_count(string $content): int {
    $t = trim($content);
    if ($t === '') { return 0; }
    // Collapse any whitespace (spaces, tabs, newlines) to single spaces
    $t = preg_replace('/\s+/u', ' ', $t);
    // Split on spaces and count tokens
    $parts = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
    return is_array($parts) ? count($parts) : 0;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['story_id']) || !isset($input['title']) || !isset($input['slug']) || !isset($input['content'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Story ID, title, slug, and content are required']);
    exit;
}

try {
    // Verify story exists
    $stmt = $pdo->prepare("SELECT id FROM stories WHERE id = ?");
    $stmt->execute([$input['story_id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Story not found']);
        exit;
    }

    // Check if slug already exists for this story
    $stmt = $pdo->prepare("SELECT id FROM chapters WHERE story_id = ? AND slug = ?");
    $stmt->execute([$input['story_id'], $input['slug']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Chapter slug already exists for this story']);
        exit;
    }

    // Check if chapter number already exists for this story
    $stmt = $pdo->prepare("SELECT id FROM chapters WHERE story_id = ? AND chapter_number = ?");
    $stmt->execute([$input['story_id'], $input['chapter_number']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Chapter number already exists for this story']);
        exit;
    }

    // Compute and persist word count
    $wordCount = compute_word_count($input['content']);

    $stmt = $pdo->prepare("
        INSERT INTO chapters (story_id, title, slug, content, soundtrack_url, chapter_number, status, word_count, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $stmt->execute([
        $input['story_id'],
        $input['title'],
        $input['slug'],
        $input['content'],
        $input['soundtrack_url'] ?? null,
        $input['chapter_number'] ?? 1,
        $input['status'] ?? 'draft',
        $wordCount
    ]);

    $chapterId = $pdo->lastInsertId();

    // Update story's updated_at timestamp
    $stmt = $pdo->prepare("UPDATE stories SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$input['story_id']]);

    echo json_encode([
        'success' => true,
        'id' => $chapterId
    ]);

} catch (Exception $e) {
    error_log("Chapter create error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create chapter']);
}
?>
