<?php
/**
 * Update a LitRPG contract/quest
 * POST /api/litrpg/contracts/update.php
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
        throw new Exception("Contract ID is required");
    }

    $contractId = intval($data['id']);

    // Check if contract exists
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_contracts WHERE id = ?");
    $checkStmt->execute([$contractId]);
    if (!$checkStmt->fetch()) {
        throw new Exception("Contract not found");
    }

    // Build UPDATE query dynamically
    $updates = [];
    $params = [];

    $allowedFields = [
        'slug', 'title', 'description', 'contract_type', 'difficulty',
        'level_requirement', 'time_limit', 'icon_image', 'status', 'sort_order'
    ];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
        }
    }

    // Handle JSON fields
    if (isset($data['objectives'])) {
        $updates[] = "objectives = ?";
        $params[] = json_encode($data['objectives']);
    }
    if (isset($data['rewards'])) {
        $updates[] = "rewards = ?";
        $params[] = json_encode($data['rewards']);
    }

    if (empty($updates)) {
        throw new Exception("No fields to update");
    }

    $params[] = $contractId;

    $sql = "UPDATE litrpg_contracts SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Fetch updated contract
    $fetchStmt = $pdo->prepare("SELECT * FROM litrpg_contracts WHERE id = ?");
    $fetchStmt->execute([$contractId]);
    $contract = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    // Decode JSON fields
    $contract['objectives'] = json_decode($contract['objectives'] ?? '[]', true);
    $contract['rewards'] = json_decode($contract['rewards'] ?? '{}', true);

    echo json_encode([
        'success' => true,
        'contract' => $contract
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
