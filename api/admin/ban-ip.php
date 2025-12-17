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

if (!$input || !isset($input['ip_address'])) {
    http_response_code(400);
    echo json_encode(['error' => 'IP address is required']);
    exit;
}

// Basic IP validation
if (!filter_var($input['ip_address'], FILTER_VALIDATE_IP)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid IP address format']);
    exit;
}

try {
    // Check if IP is already banned
    $stmt = $pdo->prepare("SELECT id FROM banned_ips WHERE ip_address = ?");
    $stmt->execute([$input['ip_address']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'IP address is already banned']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO banned_ips (ip_address, reason, banned_by, banned_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $input['ip_address'],
        $input['reason'] ?? null,
        $_SESSION['user_id']
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("IP ban error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to ban IP address']);
}
?>
