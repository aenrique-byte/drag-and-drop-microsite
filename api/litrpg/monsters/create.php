<?php
/**
 * Create a new LitRPG monster
 * POST /api/litrpg/monsters/create.php
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
    $required = ['name', 'level', 'rank'];
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
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_monsters WHERE slug = ?");
    $checkStmt->execute([$data['slug']]);
    if ($checkStmt->fetch()) {
        throw new Exception("Monster with slug '{$data['slug']}' already exists");
    }

    // Prepare data
    $fields = [
        'slug' => $data['slug'],
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'level' => intval($data['level']),
        'rank' => $data['rank'],
        'hp' => !empty($data['hp']) ? intval($data['hp']) : null,
        'xp_reward' => !empty($data['xp_reward']) ? intval($data['xp_reward']) : 0,
        'credits' => !empty($data['credits']) ? intval($data['credits']) : 0,
        'stats' => !empty($data['stats']) ? json_encode($data['stats']) : null,
        'abilities' => !empty($data['abilities']) ? json_encode($data['abilities']) : null,
        'loot_table' => !empty($data['loot_table']) ? json_encode($data['loot_table']) : null,
        'icon_image' => $data['icon_image'] ?? null,
        'status' => $data['status'] ?? 'active',
        'sort_order' => $data['sort_order'] ?? 0
    ];

    // Build INSERT query
    $columns = array_keys($fields);
    $placeholders = array_fill(0, count($columns), '?');

    $sql = "INSERT INTO litrpg_monsters (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($fields));

    $monsterId = $pdo->lastInsertId();

    // Fetch the created monster
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
