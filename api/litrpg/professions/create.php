<?php
/**
 * Create a new LitRPG profession
 * POST /api/litrpg/professions/create.php
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
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_professions WHERE slug = ?");
    $checkStmt->execute([$data['slug']]);
    if ($checkStmt->fetch()) {
        throw new Exception("Profession with slug '{$data['slug']}' already exists");
    }

    // Prepare data
    $fields = [
        'slug' => $data['slug'],
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'tier' => $data['tier'],
        'unlock_level' => intval($data['unlock_level']),
        'prerequisite_profession_id' => !empty($data['prerequisite_profession_id']) ? intval($data['prerequisite_profession_id']) : null,
        'stat_bonuses' => !empty($data['stat_bonuses']) ? json_encode($data['stat_bonuses']) : null,
        'ability_ids' => !empty($data['ability_ids']) ? json_encode($data['ability_ids']) : null,
        'icon_image' => $data['icon_image'] ?? null,
        'status' => $data['status'] ?? 'active',
        'sort_order' => $data['sort_order'] ?? 0
    ];

    // Build INSERT query
    $columns = array_keys($fields);
    $placeholders = array_fill(0, count($columns), '?');

    $sql = "INSERT INTO litrpg_professions (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($fields));

    $professionId = $pdo->lastInsertId();

    // Fetch the created profession
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
