<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Ban ID is required']);
    exit;
}

try {
    // Check if ban exists
    $stmt = $pdo->prepare("SELECT id FROM banned_ips WHERE id = ?");
    $stmt->execute([$input['id']]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Ban not found']);
        exit;
    }

    // Delete the ban
    $stmt = $pdo->prepare("DELETE FROM banned_ips WHERE id = ?");
    $stmt->execute([$input['id']]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("IP unban error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to unban IP address']);
}
?>
