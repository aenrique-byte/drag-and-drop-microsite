<?php
/**
 * Delete a LitRPG contract/quest
 * POST /api/litrpg/contracts/delete.php
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
    $checkStmt = $pdo->prepare("SELECT id, title FROM litrpg_contracts WHERE id = ?");
    $checkStmt->execute([$contractId]);
    $contract = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        throw new Exception("Contract not found");
    }

    // Soft delete (set status to archived) or hard delete
    $softDelete = $data['soft_delete'] ?? true;

    if ($softDelete) {
        $stmt = $pdo->prepare("UPDATE litrpg_contracts SET status = 'archived' WHERE id = ?");
        $stmt->execute([$contractId]);
        $message = "Contract archived successfully";
    } else {
        $stmt = $pdo->prepare("DELETE FROM litrpg_contracts WHERE id = ?");
        $stmt->execute([$contractId]);
        $message = "Contract deleted successfully";
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
