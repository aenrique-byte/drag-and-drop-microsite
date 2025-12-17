<?php
require_once __DIR__ . '/../bootstrap-litrpg.php';
header('Content-Type: application/json');
requireAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'id is required']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT id, name FROM litrpg_characters WHERE id = ?");
    $stmt->execute([$input['id']]);
    $item = $stmt->fetch();
    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Character not found']);
        exit;
    }
    
    $stmt = $pdo->prepare("DELETE FROM litrpg_characters WHERE id = ?");
    $stmt->execute([$input['id']]);
    
    echo json_encode(['success' => true, 'message' => "Character '{$item['name']}' deleted"]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
