<?php
/**
 * Blog Category Delete API
 * 
 * POST /api/blog/categories/delete.php
 * 
 * Request Body (JSON):
 * - id: int (required)
 */

require_once '../../bootstrap.php';

header('Content-Type: application/json');

// Only allow POST requests
require_method(['POST']);

// Require authentication
requireAuth();

// Rate limit: 30 requests/minute
requireRateLimit('blog:categories:delete', 30, 60, $_SESSION['user_id'], true);

try {
    $pdo = db();
    $input = body_json();
    
    // Validate ID
    if (empty($input['id'])) {
        json_error('Category ID is required', 400);
    }
    
    $categoryId = intval($input['id']);
    
    // Check if category exists and get its name
    $checkStmt = $pdo->prepare("SELECT name FROM blog_categories WHERE id = ?");
    $checkStmt->execute([$categoryId]);
    $category = $checkStmt->fetch();
    
    if (!$category) {
        json_error('Category not found', 404);
    }
    
    // Delete the category
    $deleteStmt = $pdo->prepare("DELETE FROM blog_categories WHERE id = ?");
    $deleteStmt->execute([$categoryId]);
    
    // Note: Posts that have this category will keep it in their JSON array
    // This is intentional - the category can be re-created later
    // Or you could add logic to remove it from all posts if desired
    
    json_response([
        'success' => true,
        'message' => 'Category deleted successfully'
    ]);

} catch (Exception $e) {
    error_log("Blog category delete error: " . $e->getMessage());
    json_error('Failed to delete category', 500);
}
