<?php
/**
 * Blog Post Delete API
 * 
 * POST /api/blog/delete.php
 * 
 * Request Body (JSON):
 * - id: int (required)
 * - permanent: bool (optional, default: false - soft delete to 'draft' status)
 * 
 * Soft delete changes status to 'draft' and clears scheduled_at
 * Permanent delete removes the post and cascades to comments, analytics, revisions
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');

// Only allow POST requests
require_method(['POST']);

// Require authentication
requireAuth();

// Rate limit: 5 requests/minute (authenticated users)
requireRateLimit('blog:delete', 5, 60, $_SESSION['user_id'], true);

try {
    $pdo = db();
    $input = body_json();
    
    // Validate post ID
    if (empty($input['id'])) {
        json_error('Post ID is required', 400);
    }
    
    $postId = intval($input['id']);
    $permanent = isset($input['permanent']) && $input['permanent'] === true;
    
    // Fetch existing post
    $existingStmt = $pdo->prepare("SELECT id, title, slug, status FROM blog_posts WHERE id = ?");
    $existingStmt->execute([$postId]);
    $existing = $existingStmt->fetch();
    
    if (!$existing) {
        json_error('Post not found', 404);
    }
    
    if ($permanent) {
        // Permanent delete - cascades to comments, analytics, revisions via FK constraints
        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
        $stmt->execute([$postId]);
        
        json_response([
            'success' => true,
            'message' => 'Post permanently deleted',
            'deleted_id' => $postId,
            'deleted_title' => $existing['title']
        ]);
    } else {
        // Soft delete - change status to draft, clear scheduled_at, keep published_at for reference
        $stmt = $pdo->prepare("
            UPDATE blog_posts 
            SET status = 'draft', 
                scheduled_at = NULL,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$postId]);
        
        // Fetch updated post
        $fetchStmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
        $fetchStmt->execute([$postId]);
        $post = $fetchStmt->fetch();
        
        // Process JSON fields
        $post['tags'] = !empty($post['tags']) ? json_decode($post['tags'], true) : [];
        $post['categories'] = !empty($post['categories']) ? json_decode($post['categories'], true) : [];
        
        json_response([
            'success' => true,
            'message' => 'Post moved to draft',
            'post' => $post
        ]);
    }

} catch (Exception $e) {
    error_log("Blog delete error: " . $e->getMessage());
    json_error('Failed to delete blog post', 500);
}
