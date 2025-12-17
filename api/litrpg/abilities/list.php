<?php
/**
 * List LitRPG abilities with tier information
 * GET /api/litrpg/abilities/list.php
 * Optional filters: ?category=offense&max_level=10
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

try {
    // Build query
    $sql = "SELECT * FROM litrpg_abilities WHERE status = 'active'";
    $params = [];

    // Filter by category
    if (!empty($_GET['category'])) {
        $sql .= " AND category = ?";
        $params[] = $_GET['category'];
    }

    // Filter by max_level
    if (!empty($_GET['max_level'])) {
        $sql .= " AND max_level = ?";
        $params[] = intval($_GET['max_level']);
    }

    $sql .= " ORDER BY category ASC, sort_order ASC, name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $abilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get tiers for each ability
    foreach ($abilities as &$ability) {
        $tiersStmt = $pdo->prepare("SELECT * FROM litrpg_ability_tiers WHERE ability_id = ? ORDER BY tier_level ASC");
        $tiersStmt->execute([$ability['id']]);
        $ability['tiers'] = $tiersStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true,
        'abilities' => $abilities,
        'count' => count($abilities)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
