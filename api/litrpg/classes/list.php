<?php
/**
 * List LitRPG classes
 * GET /api/litrpg/classes/list.php
 * Optional filters: ?tier=2&category=combat
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

try {
    // Build query
    $sql = "SELECT * FROM litrpg_classes WHERE status = 'active'";
    $params = [];

    // Filter by tier
    if (!empty($_GET['tier'])) {
        $sql .= " AND tier = ?";
        $params[] = intval($_GET['tier']);
    }

    $sql .= " ORDER BY tier ASC, unlock_level ASC, sort_order ASC, name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($classes as &$class) {
        $class['stat_bonuses'] = json_decode($class['stat_bonuses'] ?? '{}', true);
        $class['ability_ids'] = json_decode($class['ability_ids'] ?? '[]', true);
        $class['upgrade_ids'] = json_decode($class['upgrade_ids'] ?? '[]', true);

        // Get abilities for this class
        if (!empty($class['ability_ids'])) {
            $abilityIds = implode(',', array_map('intval', $class['ability_ids']));
            $abilityStmt = $pdo->query("SELECT id, name FROM litrpg_abilities WHERE id IN ($abilityIds) AND status = 'active'");
            $class['abilities'] = $abilityStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $class['abilities'] = [];
        }
    }

    echo json_encode([
        'success' => true,
        'classes' => $classes,
        'count' => count($classes)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
