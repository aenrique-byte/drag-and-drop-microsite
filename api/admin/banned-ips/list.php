<?php
require_once '../../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check authentication (session already started in bootstrap.php)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;

    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM banned_ips");
    $stmt->execute();
    $total = $stmt->fetchColumn();

    // Get banned IPs with user info
    $stmt = $pdo->prepare("
        SELECT b.*, u.username as banned_by_username
        FROM banned_ips b
        LEFT JOIN users u ON b.banned_by = u.id
        ORDER BY b.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $bannedIPs = $stmt->fetchAll();

    echo json_encode([
        'banned_ips' => $bannedIPs,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ]);

} catch (Exception $e) {
    error_log("Banned IPs list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load banned IPs']);
}
?>
