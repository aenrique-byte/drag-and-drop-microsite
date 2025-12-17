<?php
/**
 * Delete a LitRPG monster
 * POST /api/litrpg/monsters/delete.php
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
        throw new Exception("Monster ID is required");
    }

    $monsterId = intval($data['id']);

    // Check if monster exists
    $checkStmt = $pdo->prepare("SELECT id, name FROM litrpg_monsters WHERE id = ?");
    $checkStmt->execute([$monsterId]);
    $monster = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$monster) {
        throw new Exception("Monster not found");
    }

    // Soft delete (set status to archived) or hard delete
    $softDelete = $data['soft_delete'] ?? true;

    if ($softDelete) {
        $stmt = $pdo->prepare("UPDATE litrpg_monsters SET status = 'archived' WHERE id = ?");
        $stmt->execute([$monsterId]);
        $message = "Monster archived successfully";
    } else {
        $stmt = $pdo->prepare("DELETE FROM litrpg_monsters WHERE id = ?");
        $stmt->execute([$monsterId]);
        $message = "Monster deleted successfully";
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
