<?php
/**
 * Blog Comments List API
 * GET /api/blog/comments/list.php
 * 
 * Query params:
 * - post_id (required): Blog post ID
 * - status: Filter by status (approved, pending, spam, trash) - admin only
 * - page: Page number (default: 1)
 * - limit: Items per page (default: 50)
 * - include_replies: Include nested replies (default: true)
 */

require_once '../../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Rate limiting
requireRateLimit('blog_comments_list', 60, 60);

$postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$status = $_GET['status'] ?? 'approved';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
$includeReplies = ($_GET['include_replies'] ?? 'true') === 'true';
$offset = ($page - 1) * $limit;

if (!$postId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'post_id is required']);
    exit;
}

// Check if post exists
$stmt = $pdo->prepare("SELECT id, title, slug FROM blog_posts WHERE id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Blog post not found']);
    exit;
}

// Check if user is admin (can see all statuses)
$isAdmin = false;
try {
    $user = requireAuth();
    $isAdmin = true;
} catch (Exception $e) {
    // Not authenticated - public user
    $isAdmin = false;
}

// Public users can only see approved comments
if (!$isAdmin) {
    $status = 'approved';
}

// Build query
$whereClause = "bc.post_id = ?";
$params = [$postId];

if ($status && $status !== 'all') {
    $whereClause .= " AND bc.status = ?";
    $params[] = $status;
}

// For threaded comments, get top-level first (parent_id IS NULL)
if ($includeReplies) {
    $whereClause .= " AND bc.parent_id IS NULL";
}

// Get total count
$countSql = "SELECT COUNT(*) FROM blog_comments bc WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();

// Get comments
$sql = "
    SELECT 
        bc.id,
        bc.post_id,
        bc.parent_id,
        bc.author_name,
        bc.content,
        bc.status,
        bc.is_flagged,
        bc.created_at,
        (SELECT COUNT(*) FROM blog_comments r WHERE r.parent_id = bc.id AND r.status = 'approved') as reply_count
    FROM blog_comments bc
    WHERE $whereClause
    ORDER BY bc.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If including replies, fetch them for each comment
if ($includeReplies && !empty($comments)) {
    $commentIds = array_column($comments, 'id');
    
    if (!empty($commentIds)) {
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        
        $replySql = "
            SELECT 
                bc.id,
                bc.post_id,
                bc.parent_id,
                bc.author_name,
                bc.content,
                bc.status,
                bc.is_flagged,
                bc.created_at
            FROM blog_comments bc
            WHERE bc.parent_id IN ($placeholders)
            " . ($isAdmin ? "" : "AND bc.status = 'approved'") . "
            ORDER BY bc.created_at ASC
        ";
        
        $replyStmt = $pdo->prepare($replySql);
        $replyStmt->execute($commentIds);
        $replies = $replyStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group replies by parent_id
        $repliesByParent = [];
        foreach ($replies as $reply) {
            $repliesByParent[$reply['parent_id']][] = $reply;
        }
        
        // Attach replies to comments
        foreach ($comments as &$comment) {
            $comment['replies'] = $repliesByParent[$comment['id']] ?? [];
        }
    }
}

// Get status counts for admin
$statusCounts = null;
if ($isAdmin) {
    $countsSql = "
        SELECT 
            status,
            COUNT(*) as count
        FROM blog_comments
        WHERE post_id = ?
        GROUP BY status
    ";
    $countsStmt = $pdo->prepare($countsSql);
    $countsStmt->execute([$postId]);
    $statusCounts = [];
    while ($row = $countsStmt->fetch()) {
        $statusCounts[$row['status']] = (int)$row['count'];
    }
}

echo json_encode([
    'success' => true,
    'data' => [
        'comments' => $comments,
        'post' => [
            'id' => $post['id'],
            'title' => $post['title'],
            'slug' => $post['slug']
        ],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $totalCount,
            'total_pages' => ceil($totalCount / $limit)
        ],
        'status_counts' => $statusCounts,
        'is_admin' => $isAdmin
    ]
]);
