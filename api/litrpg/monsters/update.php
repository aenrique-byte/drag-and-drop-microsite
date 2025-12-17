<?php
/**
 * Update a LitRPG monster
 * POST /api/litrpg/monsters/update.php
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
        throw new Exception("Monster ID is required");
    }

    $monsterId = intval($data['id']);

    // Check if monster exists
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_monsters WHERE id = ?");
    $checkStmt->execute([$monsterId]);
    if (!$checkStmt->fetch()) {
        throw new Exception("Monster not found");
    }

    // Build UPDATE query dynamically
    $updates = [];
    $params = [];

    $allowedFields = [
        'slug', 'name', 'description', 'level', 'rank', 'hp',
        'xp_reward', 'credits', 'icon_image', 'status', 'sort_order'
    ];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    // Handle JSON fields
    if (isset($data['stats'])) {
        $updates[] = "stats = ?";
        $params[] = json_encode($data['stats']);
    }
    if (isset($data['abilities'])) {
        $updates[] = "abilities = ?";
        $params[] = json_encode($data['abilities']);
    }
    if (isset($data['loot_table'])) {
        $updates[] = "loot_table = ?";
        $params[] = json_encode($data['loot_table']);
    }

    if (empty($updates)) {
        throw new Exception("No fields to update");
    }

    $params[] = $monsterId;

    $sql = "UPDATE litrpg_monsters SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch updated monster
    $fetchStmt = $pdo->prepare("SELECT * FROM litrpg_monsters WHERE id = ?");
    $fetchStmt->execute([$monsterId]);
    $monster = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    // Decode JSON fields
    $monster['stats'] = json_decode($monster['stats'] ?? '{}', true);
    $monster['abilities'] = json_decode($monster['abilities'] ?? '[]', true);
    $monster['loot_table'] = json_decode($monster['loot_table'] ?? '[]', true);

    echo json_encode([
        'success' => true,
        'monster' => $monster
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
