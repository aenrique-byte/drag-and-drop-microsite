<?php
/**
 * List LitRPG items/loot
 * GET /api/litrpg/items/list.php
 * Optional filters: ?category=weapon&rarity=rare&tech_level=TL9
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

try {
    // Build query
    $sql = "SELECT * FROM litrpg_items WHERE status = 'active'";
    $params = [];

    // Filter by category
    if (!empty($_GET['category'])) {
        $sql .= " AND category = ?";
        $params[] = $_GET['category'];
    }

    // Filter by rarity
    if (!empty($_GET['rarity'])) {
        $sql .= " AND rarity = ?";
        $params[] = $_GET['rarity'];
    }

    // Filter by tech_level
    if (!empty($_GET['tech_level'])) {
        $sql .= " AND tech_level = ?";
        $params[] = $_GET['tech_level'];
    }

    $sql .= " ORDER BY category ASC, rarity ASC, sort_order ASC, name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($items as &$item) {
        $item['stats'] = json_decode($item['stats'] ?? '{}', true);
        $item['requirements'] = json_decode($item['requirements'] ?? '{}', true);
    }

    echo json_encode([
        'success' => true,
        'items' => $items,
        'count' => count($items)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
