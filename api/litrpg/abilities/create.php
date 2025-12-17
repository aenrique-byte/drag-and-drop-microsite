<?php
/**
 * Create a new LitRPG ability
 * POST /api/litrpg/abilities/create.php
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
    $required = ['name', 'max_level'];
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
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_abilities WHERE slug = ?");
    $checkStmt->execute([$data['slug']]);
    if ($checkStmt->fetch()) {
        throw new Exception("Ability with slug '{$data['slug']}' already exists");
    }

    // Prepare data
    $fields = [
        'slug' => $data['slug'],
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'max_level' => intval($data['max_level']),
        'evolution_ability_id' => !empty($data['evolution_ability_id']) ? intval($data['evolution_ability_id']) : null,
        'evolution_level' => !empty($data['evolution_level']) ? intval($data['evolution_level']) : null,
        'category' => $data['category'] ?? null,
        'icon_image' => $data['icon_image'] ?? null,
        'status' => $data['status'] ?? 'active',
        'sort_order' => $data['sort_order'] ?? 0
    ];

    // Build INSERT query
    $columns = array_keys($fields);
    $placeholders = array_fill(0, count($columns), '?');

    $sql = "INSERT INTO litrpg_abilities (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($fields));

    $abilityId = $pdo->lastInsertId();

    // Handle tiers if provided
    if (!empty($data['tiers']) && is_array($data['tiers'])) {
        $tierStmt = $pdo->prepare("INSERT INTO litrpg_ability_tiers (ability_id, tier_level, duration, cooldown, energy_cost, effect_description) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($data['tiers'] as $tier) {
            $tierStmt->execute([
                $abilityId,
                $tier['tier_level'],
                $tier['duration'] ?? null,
                $tier['cooldown'] ?? null,
                $tier['energy_cost'] ?? null,
                $tier['effect_description'] ?? null
            ]);
        }
    }

    // Fetch the created ability with tiers
    $fetchStmt = $pdo->prepare("SELECT * FROM litrpg_abilities WHERE id = ?");
    $fetchStmt->execute([$abilityId]);
    $ability = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    // Get tiers
    $tiersStmt = $pdo->prepare("SELECT * FROM litrpg_ability_tiers WHERE ability_id = ? ORDER BY tier_level ASC");
    $tiersStmt->execute([$abilityId]);
    $ability['tiers'] = $tiersStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'ability' => $ability
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
