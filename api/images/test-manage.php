<?php
// Simple test to check if manage.php path and permissions work
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // Test basic functionality
    echo json_encode([
        'success' => true,
        'message' => 'Test endpoint working',
        'method' => $_SERVER['REQUEST_METHOD'],
        'session_id' => session_id(),
        'session_started' => session_status() === PHP_SESSION_ACTIVE,
        'file_exists' => file_exists('manage.php'),
        'uploads_dir_exists' => is_dir('../uploads/'),
        'uploads_readable' => is_readable('../uploads/'),
        'current_dir' => __DIR__
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
