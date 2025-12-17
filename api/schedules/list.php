<?php
require_once '../bootstrap.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json');

try {
    global $pdo;

    $stmt = $pdo->query("
        SELECT * FROM publishing_schedules
        ORDER BY active DESC, name ASC
    ");
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format boolean
    foreach ($schedules as &$schedule) {
        $schedule['id'] = (int)$schedule['id'];
        $schedule['active'] = (bool)$schedule['active'];
    }

    echo json_encode([
        'success' => true,
        'schedules' => $schedules
    ]);

} catch (Exception $e) {
    error_log("Schedules list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load schedules']);
}
