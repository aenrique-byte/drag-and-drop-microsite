<?php
/**
 * Delete a LitRPG class
 * POST /api/litrpg/classes/delete.php
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
        throw new Exception("Class ID is required");
    }

    $classId = intval($data['id']);

    // Check if class exists
    $checkStmt = $pdo->prepare("SELECT id, name FROM litrpg_classes WHERE id = ?");
    $checkStmt->execute([$classId]);
    $class = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        throw new Exception("Class not found");
    }

    // Soft delete (set status to archived) or hard delete
    $softDelete = $data['soft_delete'] ?? true;

    if ($softDelete) {
        $stmt = $pdo->prepare("UPDATE litrpg_classes SET status = 'archived' WHERE id = ?");
        $stmt->execute([$classId]);
        $message = "Class archived successfully";
    } else {
        $stmt = $pdo->prepare("DELETE FROM litrpg_classes WHERE id = ?");
        $stmt->execute([$classId]);
        $message = "Class deleted successfully";
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
