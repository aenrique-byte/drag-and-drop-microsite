<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Accept either story_id or story_slug
$storyId = null;
if (isset($_GET['story_id'])) {
    $storyId = intval($_GET['story_id']);
} elseif (isset($_GET['story_slug'])) {
    // Look up story by slug
    $stmt = $pdo->prepare("SELECT id FROM stories WHERE slug = ?");
    $stmt->execute([$_GET['story_slug']]);
    $story = $stmt->fetch();
    if ($story) {
        $storyId = $story['id'];
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Story ID or slug is required']);
    exit;
}

if (!$storyId) {
    http_response_code(404);
    echo json_encode(['error' => 'Story not found']);
    exit;
}

try {

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(10000, intval($_GET['limit']))) : 50;
    $offset = ($page - 1) * $limit;
    
    // Check for specific chapter number filter
    $chapterNumber = isset($_GET['chapter_number']) ? intval($_GET['chapter_number']) : null;
    
    // Check if user is authenticated (for admin access) - session already started in bootstrap.php
    $isAdmin = isset($_SESSION['user_id']);

    // Only show published chapters to non-admin users
    $statusFilter = $isAdmin ? "" : " AND status = 'published'";

    if ($chapterNumber) {
        // Get specific chapter
        $stmt = $pdo->prepare("
            SELECT * FROM chapters
            WHERE story_id = ? AND chapter_number = ?" . $statusFilter . "
            LIMIT 1
        ");
        $stmt->execute([$storyId, $chapterNumber]);
        $chapters = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'chapters' => $chapters,
            'total' => count($chapters)
        ]);
    } else {
        // Get total count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chapters WHERE story_id = ?" . $statusFilter);
        $stmt->execute([$storyId]);
        $total = $stmt->fetchColumn();

        // Get chapters
        $stmt = $pdo->prepare("
            SELECT * FROM chapters
            WHERE story_id = ?" . $statusFilter . "
            ORDER BY chapter_number ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$storyId, $limit, $offset]);
        $chapters = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'chapters' => $chapters,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]);
    }

} catch (Exception $e) {
    error_log("Chapters list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load chapters']);
}
?>
