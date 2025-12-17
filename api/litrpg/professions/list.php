<?php
/**
 * List LitRPG professions
 * GET /api/litrpg/professions/list.php
 * Optional filters: ?tier=tier-1
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

try {
    // Build query
    $sql = "SELECT * FROM litrpg_professions WHERE status = 'active'";
    $params = [];

    // Filter by tier
    if (!empty($_GET['tier'])) {
        $sql .= " AND tier = ?";
        $params[] = $_GET['tier'];
    }

    $sql .= " ORDER BY tier ASC, unlock_level ASC, sort_order ASC, name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $professions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($professions as &$profession) {
        $profession['stat_bonuses'] = json_decode($profession['stat_bonuses'] ?? '{}', true);
        $profession['ability_ids'] = json_decode($profession['ability_ids'] ?? '[]', true);
    }

    echo json_encode([
        'success' => true,
        'professions' => $professions,
        'count' => count($professions)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
