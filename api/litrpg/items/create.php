<?php
/**
 * Create a new LitRPG item
 * POST /api/litrpg/items/create.php
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
    $required = ['name'];
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
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_items WHERE slug = ?");
    $checkStmt->execute([$data['slug']]);
    if ($checkStmt->fetch()) {
        throw new Exception("Item with slug '{$data['slug']}' already exists");
    }

    // Prepare data
    $fields = [
        'slug' => $data['slug'],
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'tech_level' => $data['tech_level'] ?? null,
        'category' => $data['category'] ?? null,
        'rarity' => $data['rarity'] ?? 'common',
        'base_value' => !empty($data['base_value']) ? intval($data['base_value']) : 0,
        'stats' => !empty($data['stats']) ? json_encode($data['stats']) : null,
        'requirements' => !empty($data['requirements']) ? json_encode($data['requirements']) : null,
        'icon_image' => $data['icon_image'] ?? null,
        'status' => $data['status'] ?? 'active',
        'sort_order' => $data['sort_order'] ?? 0
    ];

    // Build INSERT query
    $columns = array_keys($fields);
    $placeholders = array_fill(0, count($columns), '?');

    $sql = "INSERT INTO litrpg_items (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($fields));

    $itemId = $pdo->lastInsertId();

    // Fetch the created item
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
