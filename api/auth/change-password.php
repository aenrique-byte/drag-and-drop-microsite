<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['currentPassword']) || !isset($input['newPassword'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Current password and new password required']);
    exit;
}

$currentPassword = $input['currentPassword'];
$newPassword = $input['newPassword'];
$userId = $_SESSION['user_id'];

// Validate new password strength
if (strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'New password must be at least 8 characters long']);
    exit;
}

try {
    // Verify current password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Current password is incorrect']);
        exit;
    }

    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$newPasswordHash, $userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);

} catch (Exception $e) {
    error_log("Password change error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
