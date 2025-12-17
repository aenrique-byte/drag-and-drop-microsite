<?php
/**
 * Update a LitRPG character (Admin only)
 * POST /api/litrpg/characters/update.php
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

// Require admin authentication
requireAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Character ID required']);
        exit;
    }
    
    $id = (int)$input['id'];
    
    // Build update query dynamically based on provided fields
    $allowedFields = [
        'name', 'slug', 'description', 'level', 'xp_current', 'xp_to_level',
        'class_id', 'class_activated_at_level', 'hp_max', 'hp_current', 'ep_max', 'ep_current',
        'credits', 'portrait_image', 'header_image_url', 'status', 'sort_order',
        'profession_id', 'profession_name', 'profession_activated_at_level', 'highest_tier_achieved',
        'unspent_attribute_points'
    ];
    
    $jsonFields = [
        'stats', 'base_stats', 'equipped_items', 'inventory', 'unlocked_abilities',
        'class_history', 'class_history_with_levels', 
        'profession_history_with_levels', 'history'
    ];
    
    $updates = [];
    $params = [];
    
    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $input)) {
            $updates[] = "`$field` = ?";
            $params[] = $input[$field];
        }
    }
    
    // Handle JSON fields
    foreach ($jsonFields as $field) {
        if (array_key_exists($field, $input)) {
            $updates[] = "`$field` = ?";
            $params[] = is_array($input[$field]) ? json_encode($input[$field]) : $input[$field];
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        exit;
    }
    
    $params[] = $id;
    
    $sql = "UPDATE litrpg_characters SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() === 0) {
        // Check if character exists
        $checkStmt = $pdo->prepare("SELECT id FROM litrpg_characters WHERE id = ?");
        $checkStmt->execute([$id]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Character not found']);
            exit;
        }
    }
    
    // Return updated character
    $stmt = $pdo->prepare("SELECT * FROM litrpg_characters WHERE id = ?");
    $stmt->execute([$id]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Parse JSON fields for response
    $jsonFieldsToParse = [
        'stats', 'base_stats', 'equipped_items', 'inventory', 'unlocked_abilities',
        'class_history', 'class_history_with_levels', 
        'profession_history_with_levels', 'history'
    ];
    
    foreach ($jsonFieldsToParse as $field) {
        if (isset($character[$field]) && $character[$field]) {
            $character[$field] = json_decode($character[$field], true);
        }
    }
    
    echo json_encode([
        'success' => true,
        'character' => $character,
        'message' => 'Character updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
