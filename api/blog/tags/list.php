<?php
/**
 * Blog Tags List API
 * 
 * GET /api/blog/tags/list.php
 * 
 * Returns all unique tags used in blog posts with their post counts
 * Used for tag cloud widget and filtering
 */

require_once '../../bootstrap.php';

header('Content-Type: application/json');

// Only allow GET requests
require_method(['GET']);

// Rate limit: 60 requests/minute
requireRateLimit('blog:tags:list', 60, 60);

try {
    $pdo = db();
    
    // For public requests, only count published posts
    $isAdmin = isLoggedIn();
    $statusFilter = $isAdmin 
        ? '' 
        : "WHERE bp.status = 'published' AND bp.published_at <= NOW()";
    
    // Get all posts with their tags
    $sql = "
        SELECT bp.tags
        FROM blog_posts bp
        {$statusFilter}
    ";
    
    $stmt = $pdo->query($sql);
    $posts = $stmt->fetchAll();
    
    // Aggregate tags and count occurrences
    $tagCounts = [];
    
    foreach ($posts as $post) {
        if (!empty($post['tags'])) {
            $tags = json_decode($post['tags'], true);
            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    $tag = trim($tag);
                    if (!empty($tag)) {
                        if (!isset($tagCounts[$tag])) {
                            $tagCounts[$tag] = 0;
                        }
                        $tagCounts[$tag]++;
                    }
                }
            }
        }
    }
    
    // Sort by count (descending) then by name (ascending)
    arsort($tagCounts);
    
    // Convert to array format
    $tags = [];
    foreach ($tagCounts as $name => $count) {
        $tags[] = [
            'name' => $name,
            'slug' => strtolower(preg_replace('/[^a-z0-9]+/', '-', $name)),
            'post_count' => $count
        ];
    }
    
    json_response([
        'success' => true,
        'tags' => $tags,
        'total' => count($tags)
    ]);

} catch (Exception $e) {
    error_log("Blog tags list error: " . $e->getMessage());
    json_error('Failed to load tags', 500);
}
