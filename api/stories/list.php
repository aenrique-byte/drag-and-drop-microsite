<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    // Check if we should filter by status (for public frontend)
    $status_filter = isset($_GET['status']) ? $_GET['status'] : null;
    $slug_filter = isset($_GET['slug']) ? $_GET['slug'] : null;
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if ($status_filter) {
        $where_conditions[] = "s.status = ?";
        $params[] = $status_filter;
    }
    
    if ($slug_filter) {
        $where_conditions[] = "s.slug = ?";
        $params[] = $slug_filter;
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    // Get total count
    $count_sql = "SELECT COUNT(*) FROM stories s " . $where_clause;
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // Get stories with chapter count
    $stories_sql = "
        SELECT s.*, 
               COUNT(CASE WHEN c.status = 'published' THEN 1 END) as chapter_count,
               COALESCE(SUM(CASE WHEN c.status = 'published' THEN c.like_count ELSE 0 END), 0) as total_likes,
               COALESCE(SUM(CASE WHEN c.status = 'published' THEN c.word_count ELSE 0 END), 0) as total_words
        FROM stories s
        LEFT JOIN chapters c ON s.id = c.story_id
        " . $where_clause . "
        GROUP BY s.id
        ORDER BY s.updated_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $final_params = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($stories_sql);
    $stmt->execute($final_params);
    $stories = $stmt->fetchAll();

    // Process JSON fields for each story
    foreach ($stories as &$story) {
        // genres
        if (!empty($story['genres'])) {
            $decoded = json_decode($story['genres'], true);
            $story['genres'] = is_array($decoded) ? $decoded : [];
        } else {
            $story['genres'] = [];
        }
        // external_links: [{label, url}]
        if (!empty($story['external_links'])) {
            $decoded = json_decode($story['external_links'], true);
            $story['external_links'] = is_array($decoded) ? $decoded : [];
        } else {
            $story['external_links'] = [];
        }
    }

    echo json_encode([
        'success' => true,
        'stories' => $stories,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ]);

} catch (Exception $e) {
    error_log("Stories list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load stories']);
}
?>
