<?php
// Set error handling to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Start session
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    
    // Verify user still exists (removed status check since column doesn't exist)
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User no longer exists, clear session
        session_destroy();
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    echo json_encode([
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]
    ]);

} catch (Exception $e) {
    error_log("Auth check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    error_log("Auth check fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Fatal error: ' . $e->getMessage()]);
}
?>
