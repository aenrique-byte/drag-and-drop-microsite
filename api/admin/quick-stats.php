<?php
require_once '../bootstrap.php';

// Check authentication - session already started in bootstrap.php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json');

try {
    global $pdo;
    
    // Count all galleries (galleries table doesn't have status column)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM galleries");
    $stmt->execute();
    $galleries = $stmt->fetchColumn();
    
    // Count published stories
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stories WHERE status = 'published'");
    $stmt->execute();
    $stories = $stmt->fetchColumn();
    
    // Count total comments (image + chapter comments)
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM image_comments) + 
            (SELECT COUNT(*) FROM chapter_comments) as total_comments
    ");
    $stmt->execute();
    $comments = $stmt->fetchColumn();
    
    // Count total likes (image + chapter likes)
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM image_likes) + 
            (SELECT COUNT(*) FROM chapter_likes) as total_likes
    ");
    $stmt->execute();
    $likes = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'galleries' => (int)$galleries,
            'stories' => (int)$stories,
            'comments' => (int)$comments,
            'total_likes' => (int)$likes
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Quick stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch quick stats', 'debug' => $e->getMessage()]);
}
?>
