<?php
/**
 * List LitRPG contracts/quests
 * GET /api/litrpg/contracts/list.php
 * Optional filters: ?difficulty=hazardous&contract_type=bounty
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

try {
    // Build query
    $sql = "SELECT * FROM litrpg_contracts WHERE status = 'active'";
    $params = [];

    // Filter by difficulty
    if (!empty($_GET['difficulty'])) {
        $sql .= " AND difficulty = ?";
        $params[] = $_GET['difficulty'];
    }

    // Filter by contract_type
    if (!empty($_GET['contract_type'])) {
        $sql .= " AND contract_type = ?";
        $params[] = $_GET['contract_type'];
    }

    // Filter by level_requirement
    if (!empty($_GET['level_requirement'])) {
        $sql .= " AND level_requirement = ?";
        $params[] = intval($_GET['level_requirement']);
    }

    $sql .= " ORDER BY difficulty ASC, level_requirement ASC, sort_order ASC, title ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON fields
    foreach ($contracts as &$contract) {
        $contract['objectives'] = json_decode($contract['objectives'] ?? '[]', true);
        $contract['rewards'] = json_decode($contract['rewards'] ?? '{}', true);
    }

    echo json_encode([
        'success' => true,
        'contracts' => $contracts,
        'count' => count($contracts)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
