<?php
/**
 * Blog Comment Moderation API
 * POST /api/blog/comments/moderate.php
 * 
 * Requires authentication.
 * 
 * Request body:
 * - comment_ids (required): Array of comment IDs to moderate
 * - action (required): One of 'approve', 'reject', 'spam', 'trash', 'delete'
 * 
 * Actions:
 * - approve: Set status to 'approved'
 * - reject: Set status to 'pending' (back to review)
 * - spam: Set status to 'spam'
 * - trash: Set status to 'trash'
 * - delete: Permanently delete the comment(s)
 */

require_once '../../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Require authentication
$user = requireAuth();

// Rate limiting
requireRateLimit('blog_comment_moderate', 100, 60);

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$commentIds = $input['comment_ids'] ?? [];
$action = trim($input['action'] ?? '');

if (empty($commentIds) || !is_array($commentIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'comment_ids must be a non-empty array']);
    exit;
}

$validActions = ['approve', 'reject', 'spam', 'trash', 'delete'];
if (!in_array($action, $validActions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action. Must be one of: ' . implode(', ', $validActions)]);
    exit;
}

// Sanitize comment IDs
$commentIds = array_map('intval', $commentIds);
$commentIds = array_filter($commentIds, function($id) { return $id > 0; });

if (empty($commentIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid comment IDs provided']);
    exit;
}

// Map action to status
$statusMap = [
    'approve' => 'approved',
    'reject' => 'pending',
    'spam' => 'spam',
    'trash' => 'trash'
];

try {
    $pdo->beginTransaction();
    
    $affectedCount = 0;
    $affectedPosts = [];
    
    if ($action === 'delete') {
        // Permanently delete comments
        // First, get the post IDs for these comments
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        
        $stmt = $pdo->prepare("SELECT DISTINCT post_id FROM blog_comments WHERE id IN ($placeholders)");
        $stmt->execute($commentIds);
        $affectedPosts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Delete the comments
        $stmt = $pdo->prepare("DELETE FROM blog_comments WHERE id IN ($placeholders)");
        $stmt->execute($commentIds);
        $affectedCount = $stmt->rowCount();
        
    } else {
        // Update status
        $newStatus = $statusMap[$action];
        $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
        
        // Get the post IDs for these comments before updating
        $stmt = $pdo->prepare("SELECT DISTINCT post_id FROM blog_comments WHERE id IN ($placeholders)");
        $stmt->execute($commentIds);
        $affectedPosts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Update the status
        $params = array_merge([$newStatus], $commentIds);
        $stmt = $pdo->prepare("UPDATE blog_comments SET status = ? WHERE id IN ($placeholders)");
        $stmt->execute($params);
        $affectedCount = $stmt->rowCount();
    }
    
    // Update comment counts for affected posts
    foreach ($affectedPosts as $postId) {
        $stmt = $pdo->prepare("
            UPDATE blog_posts 
            SET comment_count = (
                SELECT COUNT(*) FROM blog_comments 
                WHERE post_id = ? AND status = 'approved'
            )
            WHERE id = ?
        ");
        $stmt->execute([$postId, $postId]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($action) . "d $affectedCount comment(s)",
        'affected_count' => $affectedCount,
        'affected_posts' => $affectedPosts
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Blog comment moderation failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to moderate comments']);
}
