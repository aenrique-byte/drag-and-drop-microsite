<?php
/**
 * Update a LitRPG class
 * POST /api/litrpg/classes/update.php
 * Requires admin authentication
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

// Check authentication
requireAuth();
requireAdmin();

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        throw new Exception("Class ID is required");
    }

    $classId = intval($data['id']);

    // Check if class exists
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_classes WHERE id = ?");
    $checkStmt->execute([$classId]);
    if (!$checkStmt->fetch()) {
        throw new Exception("Class not found");
    }

    // Build UPDATE query dynamically
    $updates = [];
    $params = [];

    $allowedFields = [
        'slug', 'name', 'description', 'tier', 'unlock_level',
        'prerequisite_class_id', 'primary_attribute', 'secondary_attribute',
        'starting_item', 'icon_image', 'status', 'sort_order'
    ];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    // Handle JSON fields
    if (isset($data['stat_bonuses'])) {
        $updates[] = "stat_bonuses = ?";
        $params[] = json_encode($data['stat_bonuses']);
    }
    if (isset($data['ability_ids'])) {
        $updates[] = "ability_ids = ?";
        $params[] = json_encode($data['ability_ids']);
    }
    if (isset($data['upgrade_ids'])) {
        $updates[] = "upgrade_ids = ?";
        $params[] = json_encode($data['upgrade_ids']);
    }

    if (empty($updates)) {
        throw new Exception("No fields to update");
    }

    $params[] = $classId;

    $sql = "UPDATE litrpg_classes SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch updated class
    $fetchStmt = $pdo->prepare("SELECT * FROM litrpg_classes WHERE id = ?");
    $fetchStmt->execute([$classId]);
    $class = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    // Decode JSON fields
    $class['stat_bonuses'] = json_decode($class['stat_bonuses'] ?? '{}', true);
    $class['ability_ids'] = json_decode($class['ability_ids'] ?? '[]', true);
    $class['upgrade_ids'] = json_decode($class['upgrade_ids'] ?? '[]', true);

    echo json_encode([
        'success' => true,
        'class' => $class
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
