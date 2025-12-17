<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check authentication - session already started in bootstrap.php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    // Get comment stats from both image and chapter comments
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN is_approved = 1 THEN 'approved'
                ELSE 'pending'
            END as status,
            COUNT(*) as count
        FROM (
            SELECT is_approved FROM image_comments
            UNION ALL
            SELECT is_approved FROM chapter_comments
        ) all_comments
        GROUP BY is_approved
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $commentStats = [];
    foreach ($results as $row) {
        $commentStats[$row['status']] = $row['count'];
    }

    // Get banned IPs count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM banned_ips");
    $stmt->execute();
    $bannedIPs = $stmt->fetchColumn();

    // Get total galleries count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM galleries");
    $stmt->execute();
    $totalGalleries = $stmt->fetchColumn();

    // Get total stories count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stories");
    $stmt->execute();
    $totalStories = $stmt->fetchColumn();

    // Get total images count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM images");
    $stmt->execute();
    $totalImages = $stmt->fetchColumn();

    // Get total chapters count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chapters");
    $stmt->execute();
    $totalChapters = $stmt->fetchColumn();

    echo json_encode([
        'pending_comments' => $commentStats['pending'] ?? 0,
        'approved_comments' => $commentStats['approved'] ?? 0,
        'rejected_comments' => $commentStats['rejected'] ?? 0,
        'banned_ips' => $bannedIPs,
        'total_galleries' => $totalGalleries,
        'total_stories' => $totalStories,
        'total_images' => $totalImages,
        'total_chapters' => $totalChapters
    ]);

} catch (Exception $e) {
    error_log("Moderation stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load moderation stats']);
}
?>
