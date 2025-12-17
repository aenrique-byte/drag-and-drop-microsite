<?php
/**
 * Delete a LitRPG ability
 * POST /api/litrpg/abilities/delete.php
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
        throw new Exception("Ability ID is required");
    }

    $abilityId = intval($data['id']);

    // Check if ability exists
    $checkStmt = $pdo->prepare("SELECT id, name FROM litrpg_abilities WHERE id = ?");
    $checkStmt->execute([$abilityId]);
    $ability = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$ability) {
        throw new Exception("Ability not found");
    }

    // Soft delete (set status to archived) or hard delete
    $softDelete = $data['soft_delete'] ?? true;

    if ($softDelete) {
        $stmt = $pdo->prepare("UPDATE litrpg_abilities SET status = 'archived' WHERE id = ?");
        $stmt->execute([$abilityId]);
        $message = "Ability archived successfully";
    } else {
        // Hard delete - tiers will be cascade deleted due to foreign key
        $stmt = $pdo->prepare("DELETE FROM litrpg_abilities WHERE id = ?");
        $stmt->execute([$abilityId]);
        $message = "Ability deleted successfully";
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
