<?php
require_once '../bootstrap.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Schedule ID required']);
    exit;
}

try {
    global $pdo;

    // Check if any stories are using this schedule
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stories WHERE schedule_id = ?");
    $stmt->execute([$input['id']]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        http_response_code(400);
        echo json_encode([
            'error' => "Cannot delete schedule: {$count} stor" . ($count === 1 ? 'y is' : 'ies are') . " using it"
        ]);
        exit;
    }

    // Delete the schedule
    $stmt = $pdo->prepare("DELETE FROM publishing_schedules WHERE id = ?");
    $stmt->execute([$input['id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Schedule deleted successfully'
    ]);

} catch (Exception $e) {
    error_log("Schedule delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete schedule: ' . $e->getMessage()]);
}
