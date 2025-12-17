<?php
/**
 * Update a LitRPG item
 * POST /api/litrpg/items/update.php
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
        throw new Exception("Item ID is required");
    }

    $itemId = intval($data['id']);

    // Check if item exists
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_items WHERE id = ?");
    $checkStmt->execute([$itemId]);
    if (!$checkStmt->fetch()) {
        throw new Exception("Item not found");
    }

    // Build UPDATE query dynamically
    $updates = [];
    $params = [];

    $allowedFields = [
        'slug', 'name', 'description', 'tech_level', 'category',
        'rarity', 'base_value', 'icon_image', 'status', 'sort_order'
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
    if (isset($data['requirements'])) {
        $updates[] = "requirements = ?";
        $params[] = json_encode($data['requirements']);
    }

    if (empty($updates)) {
        throw new Exception("No fields to update");
    }

    $params[] = $itemId;

    $sql = "UPDATE litrpg_items SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch updated item
    $fetchStmt = $pdo->prepare("SELECT * FROM litrpg_items WHERE id = ?");
    $fetchStmt->execute([$itemId]);
    $item = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    // Decode JSON fields
    $item['stats'] = json_decode($item['stats'] ?? '{}', true);
    $item['requirements'] = json_decode($item['requirements'] ?? '{}', true);

    echo json_encode([
        'success' => true,
        'item' => $item
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
