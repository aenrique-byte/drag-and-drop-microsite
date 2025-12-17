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

    // Check if slug is being changed and if it conflicts
    if (isset($input['slug'])) {
        $stmt = $pdo->prepare("SELECT id FROM chapters WHERE story_id = ? AND slug = ? AND id != ?");
        $stmt->execute([$chapter['story_id'], $input['slug'], $input['id']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Chapter slug already exists for this story']);
            exit;
        }
    }

    // Check if chapter number is being changed and if it conflicts
    if (isset($input['chapter_number'])) {
        $stmt = $pdo->prepare("SELECT id FROM chapters WHERE story_id = ? AND chapter_number = ? AND id != ?");
        $stmt->execute([$chapter['story_id'], $input['chapter_number'], $input['id']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Chapter number already exists for this story']);
            exit;
        }
    }

    // Build dynamic UPDATE query based on provided fields
    $updateFields = [];
    $updateValues = [];

    $allowedFields = ['title', 'slug', 'content', 'soundtrack_url', 'chapter_number', 'status'];

foreach ($allowedFields as $field) {
    if (isset($input[$field])) {
        $updateFields[] = "$field = ?";
        $updateValues[] = $input[$field];
    }
}
// If content provided, recompute and persist word_count
if (isset($input['content'])) {
    $wordCount = compute_word_count($input['content']);
    $updateFields[] = "word_count = ?";
    $updateValues[] = $wordCount;
}

    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }

    // Always update the timestamp
    $updateFields[] = "updated_at = NOW()";

    $sql = "UPDATE chapters SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $updateValues[] = $input['id'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($updateValues);

    // Update story's updated_at timestamp
    $stmt = $pdo->prepare("UPDATE stories SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$chapter['story_id']]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Chapter update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update chapter']);
}
?>
