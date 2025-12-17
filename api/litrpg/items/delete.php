<?php
/**
 * Delete a LitRPG item
 * POST /api/litrpg/items/delete.php
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
        throw new Exception("Item ID is required");
    }

    $itemId = intval($data['id']);

    // Check if item exists
    $checkStmt = $pdo->prepare("SELECT id, name FROM litrpg_items WHERE id = ?");
    $checkStmt->execute([$itemId]);
    $item = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Item not found");
    }

    // Soft delete (set status to archived) or hard delete
    $softDelete = $data['soft_delete'] ?? true;

    if ($softDelete) {
        $stmt = $pdo->prepare("UPDATE litrpg_items SET status = 'archived' WHERE id = ?");
        $stmt->execute([$itemId]);
        $message = "Item archived successfully";
    } else {
        $stmt = $pdo->prepare("DELETE FROM litrpg_items WHERE id = ?");
        $stmt->execute([$itemId]);
        $message = "Item deleted successfully";
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
