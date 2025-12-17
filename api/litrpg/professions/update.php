<?php
/**
 * Update a LitRPG profession
 * POST /api/litrpg/professions/update.php
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
        throw new Exception("Profession ID is required");
    }

    $professionId = intval($data['id']);

    // Check if profession exists
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_professions WHERE id = ?");
    $checkStmt->execute([$professionId]);
    if (!$checkStmt->fetch()) {
        throw new Exception("Profession not found");
    }

    // Build UPDATE query dynamically
    $updates = [];
    $params = [];

    $allowedFields = [
        'slug', 'name', 'description', 'tier', 'unlock_level',
        'prerequisite_profession_id', 'icon_image', 'status', 'sort_order'
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

    if (empty($updates)) {
        throw new Exception("No fields to update");
    }

    $params[] = $professionId;

    $sql = "UPDATE litrpg_professions SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch updated profession
    $fetchStmt = $pdo->prepare("SELECT * FROM litrpg_professions WHERE id = ?");
    $fetchStmt->execute([$professionId]);
    $profession = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    // Decode JSON fields
    $profession['stat_bonuses'] = json_decode($profession['stat_bonuses'] ?? '{}', true);
    $profession['ability_ids'] = json_decode($profession['ability_ids'] ?? '[]', true);

    echo json_encode([
        'success' => true,
        'profession' => $profession
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
