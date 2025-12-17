<?php
/**
 * Blog Categories List API
 * 
 * GET /api/blog/categories/list.php
 * 
 * Returns all blog categories with post counts
 * 
 * Query Parameters:
 * - include_empty: bool (default: false) - Include categories with zero posts
 */

require_once '../../bootstrap.php';

header('Content-Type: application/json');

// Only allow GET requests
require_method(['GET']);

// Rate limit: 60 requests/minute
requireRateLimit('blog:categories:list', 60, 60);

try {
    $pdo = db();
    
    $includeEmpty = isset($_GET['include_empty']) && $_GET['include_empty'] === 'true';
    
    // Get all categories
    $sql = "
        SELECT 
            bc.id,
            bc.slug,
            bc.name,
            bc.description,
            bc.sort_order,
            bc.created_at
        FROM blog_categories bc
        ORDER BY bc.sort_order ASC, bc.name ASC
    ";
    
    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll();
    
    // Get post counts for each category (only published posts for public)
    $isAdmin = isLoggedIn();
    $statusFilter = $isAdmin ? '' : "AND bp.status = 'published' AND bp.published_at <= NOW()";
    
    foreach ($categories as &$category) {
        // Count posts that have this category in their JSON array
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM blog_posts bp 
            WHERE JSON_CONTAINS(bp.categories, ?, '$')
            {$statusFilter}
        ");
        $countStmt->execute([json_encode($category['name'])]);
        $category['post_count'] = (int)$countStmt->fetchColumn();
    }
    
    // Filter out empty categories if requested
    if (!$includeEmpty) {
        $categories = array_values(array_filter($categories, function($cat) {
            return $cat['post_count'] > 0;
        }));
    }
    
    json_response([
        'success' => true,
        'categories' => $categories
    ]);

} catch (Exception $e) {
    error_log("Blog categories list error: " . $e->getMessage());
    json_error('Failed to load categories', 500);
}
