<?php
/**
 * Blog Posts List API
 * 
 * GET /api/blog/list.php
 * 
 * Query Parameters:
 * - page: Page number (default: 1)
 * - limit: Items per page (default: 10, max: 50)
 * - status: Filter by status (draft, published, scheduled)
 * - universe: Filter by universe_tag
 * - category: Filter by category
 * - tag: Filter by tag
 * - q: Search query (searches title, excerpt)
 * - sort: Sort field (published_at, created_at, view_count, title) (default: published_at)
 * - order: Sort order (ASC, DESC) (default: DESC)
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');

// Only allow GET requests
require_method(['GET']);

// Rate limit: 60 requests/minute
requireRateLimit('blog:list', 60, 60);

try {
    $pdo = db();
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;
    
    // Filters
    $status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : null;
    $universe = isset($_GET['universe']) ? sanitizeInput($_GET['universe']) : null;
    $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : null;
    $tag = isset($_GET['tag']) ? sanitizeInput($_GET['tag']) : null;
    $search = isset($_GET['q']) ? sanitizeInput($_GET['q']) : null;
    
    // Sorting
    $allowedSorts = ['published_at', 'created_at', 'view_count', 'title', 'updated_at'];
    $sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts) ? $_GET['sort'] : 'published_at';
    $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    // For public requests, only show published posts
    // Admin can see all statuses by passing status parameter
    if (!isLoggedIn()) {
        $where[] = "bp.status = 'published'";
        $where[] = "bp.published_at <= NOW()";
    } elseif ($status) {
        $where[] = "bp.status = ?";
        $params[] = $status;
    }
    
    if ($universe) {
        $where[] = "bp.universe_tag = ?";
        $params[] = $universe;
    }
    
    if ($category) {
        $where[] = "JSON_CONTAINS(bp.categories, ?, '$')";
        $params[] = json_encode($category);
    }
    
    if ($tag) {
        $where[] = "JSON_CONTAINS(bp.tags, ?, '$')";
        $params[] = json_encode($tag);
    }
    
    if ($search) {
        $where[] = "(bp.title LIKE ? OR bp.excerpt LIKE ?)";
        $searchPattern = '%' . $search . '%';
        $params[] = $searchPattern;
        $params[] = $searchPattern;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM blog_posts bp {$whereClause}";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    
    // Get posts with author info
    $sql = "
        SELECT 
            bp.id,
            bp.slug,
            bp.title,
            bp.excerpt,
            bp.cover_image,
            bp.featured_image_id,
            bp.tags,
            bp.categories,
            bp.universe_tag,
            bp.status,
            bp.published_at,
            bp.scheduled_at,
            bp.reading_time,
            bp.view_count,
            bp.like_count,
            bp.comment_count,
            bp.created_at,
            bp.updated_at,
            u.username as author_name,
            u.id as author_id
        FROM blog_posts bp
        LEFT JOIN users u ON bp.author_id = u.id
        {$whereClause}
        ORDER BY bp.{$sort} {$order}
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
    
    // Process JSON fields
    foreach ($posts as &$post) {
        $post['tags'] = !empty($post['tags']) ? json_decode($post['tags'], true) : [];
        $post['categories'] = !empty($post['categories']) ? json_decode($post['categories'], true) : [];
        
        // Convert counts to integers
        $post['view_count'] = (int)$post['view_count'];
        $post['like_count'] = (int)$post['like_count'];
        $post['comment_count'] = (int)$post['comment_count'];
        $post['reading_time'] = $post['reading_time'] ? (int)$post['reading_time'] : null;
    }
    
    json_response([
        'success' => true,
        'posts' => $posts,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ]);

} catch (Exception $e) {
    error_log("Blog list error: " . $e->getMessage());
    json_error('Failed to load blog posts', 500);
}
