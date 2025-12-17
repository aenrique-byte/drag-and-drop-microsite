<?php
/**
 * Create a new LitRPG class
 * POST /api/litrpg/classes/create.php
 * Requires admin authentication
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

// Check authentication
requireAuth();
requireAdmin();

try {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields (slug is now optional)
    $required = ['name', 'tier', 'unlock_level'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            throw new Exception("Missing required field: $field");
        }
    }

    // Auto-generate slug from name if not provided
    if (empty($data['slug'])) {
        $data['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name'])));
    }

    // Check for duplicate slug
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_classes WHERE slug = ?");
    $checkStmt->execute([$data['slug']]);
    if ($checkStmt->fetch()) {
        throw new Exception("Class with slug '{$data['slug']}' already exists");
    }

    // Prepare data
    $fields = [
        'slug' => $data['slug'],
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'tier' => intval($data['tier']),
        'unlock_level' => intval($data['unlock_level']),
        'prerequisite_class_id' => !empty($data['prerequisite_class_id']) ? intval($data['prerequisite_class_id']) : null,
        'stat_bonuses' => !empty($data['stat_bonuses']) ? json_encode($data['stat_bonuses']) : null,
        'primary_attribute' => $data['primary_attribute'] ?? null,
        'secondary_attribute' => $data['secondary_attribute'] ?? null,
        'starting_item' => $data['starting_item'] ?? null,
        'ability_ids' => !empty($data['ability_ids']) ? json_encode($data['ability_ids']) : null,
        'upgrade_ids' => !empty($data['upgrade_ids']) ? json_encode($data['upgrade_ids']) : null,
        'icon_image' => $data['icon_image'] ?? null,
        'status' => $data['status'] ?? 'active',
        'sort_order' => $data['sort_order'] ?? 0
    ];

    // Build INSERT query
    $columns = array_keys($fields);
    $placeholders = array_fill(0, count($columns), '?');

    $sql = "INSERT INTO litrpg_classes (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($fields));

    $classId = $pdo->lastInsertId();

    // Fetch the created class
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
