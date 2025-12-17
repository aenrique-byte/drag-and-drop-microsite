<?php
/**
 * Create a new LitRPG contract/quest
 * POST /api/litrpg/contracts/create.php
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
    $required = ['title', 'level_requirement'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            throw new Exception("Missing required field: $field");
        }
    }

    // Auto-generate slug from title if not provided
    if (empty($data['slug'])) {
        $data['slug'] = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title'])));
    }

    // Check for duplicate slug
    $checkStmt = $pdo->prepare("SELECT id FROM litrpg_contracts WHERE slug = ?");
    $checkStmt->execute([$data['slug']]);
    if ($checkStmt->fetch()) {
        throw new Exception("Contract with slug '{$data['slug']}' already exists");
    }

    // Prepare data
    $fields = [
        'slug' => $data['slug'],
        'title' => $data['title'],
        'description' => $data['description'] ?? null,
        'contract_type' => $data['contract_type'] ?? null,
        'difficulty' => $data['difficulty'] ?? 'routine',
        'level_requirement' => intval($data['level_requirement']),
        'time_limit' => $data['time_limit'] ?? null,
        'objectives' => !empty($data['objectives']) ? json_encode($data['objectives']) : null,
        'rewards' => !empty($data['rewards']) ? json_encode($data['rewards']) : null,
        'icon_image' => $data['icon_image'] ?? null,
        'status' => $data['status'] ?? 'active',
        'sort_order' => $data['sort_order'] ?? 0
    ];

    // Build INSERT query
    $columns = array_keys($fields);
    $placeholders = array_fill(0, count($columns), '?');

    $sql = "INSERT INTO litrpg_contracts (" . implode(', ', $columns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($fields));

    $contractId = $pdo->lastInsertId();

    // Fetch the created contract
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
