<?php
/**
 * Blog Post Get API
 * 
 * GET /api/blog/get.php
 * 
 * Query Parameters:
 * - slug: Post slug (required if id not provided)
 * - id: Post ID (required if slug not provided)
 * - include_related: Include related posts (default: false)
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');

// Only allow GET requests
require_method(['GET']);

// Rate limit: 100 requests/minute
requireRateLimit('blog:get', 100, 60);

try {
    $pdo = db();
    
    $slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : null;
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $includeRelated = isset($_GET['include_related']) && $_GET['include_related'] === 'true';
    
    if (!$slug && !$id) {
        json_error('Either slug or id is required', 400);
    }
    
    // Build query
    $where = $slug ? "bp.slug = ?" : "bp.id = ?";
    $param = $slug ?: $id;
    
    // For non-authenticated users, only show published posts
    if (!isLoggedIn()) {
        $where .= " AND bp.status = 'published' AND bp.published_at <= NOW()";
    }
    
    $sql = "
        SELECT 
            bp.*,
            u.username as author_name
        FROM blog_posts bp
        LEFT JOIN users u ON bp.author_id = u.id
        WHERE {$where}
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$param]);
    $post = $stmt->fetch();
    
    if (!$post) {
        json_error('Post not found', 404);
    }
    
    // Process JSON fields
    $post['tags'] = !empty($post['tags']) ? json_decode($post['tags'], true) : [];
    $post['categories'] = !empty($post['categories']) ? json_decode($post['categories'], true) : [];
    
    // Convert numeric fields
    $post['view_count'] = (int)$post['view_count'];
    $post['like_count'] = (int)$post['like_count'];
    $post['comment_count'] = (int)$post['comment_count'];
    $post['reading_time'] = $post['reading_time'] ? (int)$post['reading_time'] : null;
    
    // Get featured image details if exists
    if ($post['featured_image_id']) {
        $imgStmt = $pdo->prepare("
            SELECT id, original_path, thumbnail_path, alt_text, width, height, prompt, checkpoint
            FROM images 
            WHERE id = ?
        ");
        $imgStmt->execute([$post['featured_image_id']]);
        $post['featured_image'] = $imgStmt->fetch() ?: null;
    } else {
        $post['featured_image'] = null;
    }
    
    // Get related posts if requested
    $relatedPosts = [];
    if ($includeRelated) {
        // Find posts with matching tags, categories, or universe
        $relatedSql = "
            SELECT 
                bp.id,
                bp.slug,
                bp.title,
                bp.excerpt,
                bp.cover_image,
                bp.tags,
                bp.categories,
                bp.universe_tag,
                bp.published_at,
                bp.reading_time,
                bp.view_count
            FROM blog_posts bp
            WHERE bp.id != ?
            AND bp.status = 'published'
            AND bp.published_at <= NOW()
            AND (
                bp.universe_tag = ?
                OR JSON_OVERLAPS(bp.tags, ?)
                OR JSON_OVERLAPS(bp.categories, ?)
            )
            ORDER BY 
                CASE WHEN bp.universe_tag = ? THEN 1 ELSE 0 END DESC,
                bp.published_at DESC
            LIMIT 5
        ";
        
        $tagsJson = json_encode($post['tags'] ?: []);
        $categoriesJson = json_encode($post['categories'] ?: []);
        
        $relatedStmt = $pdo->prepare($relatedSql);
        $relatedStmt->execute([
            $post['id'],
            $post['universe_tag'],
            $tagsJson,
            $categoriesJson,
            $post['universe_tag']
        ]);
        $relatedPosts = $relatedStmt->fetchAll();
        
        // Process related posts JSON fields
        foreach ($relatedPosts as &$related) {
            $related['tags'] = !empty($related['tags']) ? json_decode($related['tags'], true) : [];
            $related['categories'] = !empty($related['categories']) ? json_decode($related['categories'], true) : [];
            $related['view_count'] = (int)$related['view_count'];
            $related['reading_time'] = $related['reading_time'] ? (int)$related['reading_time'] : null;
        }
    }
    
    $response = [
        'success' => true,
        'post' => $post
    ];
    
    if ($includeRelated) {
        $response['related_posts'] = $relatedPosts;
    }
    
    json_response($response);

} catch (Exception $e) {
    error_log("Blog get error: " . $e->getMessage());
    json_error('Failed to load blog post', 500);
}
