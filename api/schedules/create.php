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

// Validate required fields
if (!isset($input['name']) || !isset($input['frequency']) || !isset($input['time']) || !isset($input['timezone'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Validate frequency
if (!in_array($input['frequency'], ['daily', 'weekly'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid frequency']);
    exit;
}

// Validate days_of_week for weekly schedules
if ($input['frequency'] === 'weekly') {
    if (empty($input['days_of_week'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Weekly schedules require days_of_week']);
        exit;
    }
    // Validate format: comma-separated numbers 0-6
    if (!preg_match('/^[0-6](,[0-6])*$/', $input['days_of_week'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid days_of_week format']);
        exit;
    }
}

try {
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO publishing_schedules (name, frequency, time, timezone, days_of_week, active)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $input['name'],
        $input['frequency'],
        $input['time'],
        $input['timezone'],
        $input['frequency'] === 'daily' ? null : $input['days_of_week'],
        isset($input['active']) ? (int)$input['active'] : 1
    ]);

    $scheduleId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'id' => $scheduleId,
        'message' => 'Schedule created successfully'
    ]);

} catch (Exception $e) {
    error_log("Schedule create error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create schedule: ' . $e->getMessage()]);
}
