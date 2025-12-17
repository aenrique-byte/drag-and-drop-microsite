<?php
/**
 * Delete a LitRPG profession
 * POST /api/litrpg/professions/delete.php
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
        throw new Exception("Profession ID is required");
    }

    $professionId = intval($data['id']);

    // Check if profession exists
    $checkStmt = $pdo->prepare("SELECT id, name FROM litrpg_professions WHERE id = ?");
    $checkStmt->execute([$professionId]);
    $profession = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$profession) {
        throw new Exception("Profession not found");
    }

    // Soft delete (set status to archived) or hard delete
    $softDelete = $data['soft_delete'] ?? true;

    if ($softDelete) {
        $stmt = $pdo->prepare("UPDATE litrpg_professions SET status = 'archived' WHERE id = ?");
        $stmt->execute([$professionId]);
        $message = "Profession archived successfully";
    } else {
        $stmt = $pdo->prepare("DELETE FROM litrpg_professions WHERE id = ?");
        $stmt->execute([$professionId]);
        $message = "Profession deleted successfully";
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
