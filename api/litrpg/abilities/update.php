<?php
/**
 * Update a LitRPG ability
 * POST /api/litrpg/abilities/update.php
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
        throw new Exception("Ability ID is required");
    }

    $abilityId = intval($data['id']);

    // Check if ability exists
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_abilities WHERE id = ?");
    $checkStmt->execute([$abilityId]);
    if (!$checkStmt->fetch()) {
        throw new Exception("Ability not found");
    }

    // Build UPDATE query dynamically
    $updates = [];
    $params = [];

    $allowedFields = [
        'slug', 'name', 'description', 'max_level', 'evolution_ability_id',
        'evolution_level', 'category', 'icon_image', 'status', 'sort_order'
    ];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    if (!empty($updates)) {
        $params[] = $abilityId;
        $sql = "UPDATE litrpg_abilities SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    // Handle tiers if provided
    if (isset($data['tiers']) && is_array($data['tiers'])) {
        // Delete existing tiers
        $deleteStmt = $pdo->prepare("DELETE FROM litrpg_ability_tiers WHERE ability_id = ?");
        $deleteStmt->execute([$abilityId]);

        // Insert new tiers
        if (!empty($data['tiers'])) {
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
    }

    // Fetch updated ability with tiers
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
