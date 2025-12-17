<?php
/**
 * List LitRPG monsters
 * GET /api/litrpg/monsters/list.php
 * Optional filters: ?rank=Boss&level=10
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

try {
    // Build query
    $sql = "SELECT * FROM litrpg_monsters WHERE status = 'active'";
    $params = [];

    // Filter by rank
    if (!empty($_GET['rank'])) {
        $sql .= " AND rank = ?";
        $params[] = $_GET['rank'];
    }

    // Filter by level
    if (!empty($_GET['level'])) {
        $sql .= " AND level = ?";
        $params[] = intval($_GET['level']);
    }

    $sql .= " ORDER BY level ASC, rank ASC, sort_order ASC, name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $monsters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($monsters as &$monster) {
        $monster['stats'] = json_decode($monster['stats'] ?? '{}', true);
        $monster['abilities'] = json_decode($monster['abilities'] ?? '[]', true);
        $monster['loot_table'] = json_decode($monster['loot_table'] ?? '[]', true);
    }

    echo json_encode([
        'success' => true,
        'monsters' => $monsters,
        'count' => count($monsters)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
