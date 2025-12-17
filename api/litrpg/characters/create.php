<?php
/**
 * Create a new LitRPG character (Admin only)
 * POST /api/litrpg/characters/create.php
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

// Require admin authentication
requireAdmin();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'name is required']);
        exit;
    }
    
    // Generate unique slug if not provided
    $slug = $input['slug'] ?? generateUniqueSlug($pdo, 'litrpg_characters', $input['name']);
    
    // Default stats
    $defaultStats = ['STR' => 10, 'PER' => 10, 'DEX' => 10, 'MEM' => 10, 'INT' => 10, 'CHA' => 10];
    $stats = isset($input['stats']) && is_array($input['stats']) 
        ? json_encode(array_merge($defaultStats, $input['stats'])) 
        : json_encode($defaultStats);
    
    // Handle JSON fields
    $equippedItems = isset($input['equipped_items']) && is_array($input['equipped_items']) 
        ? json_encode($input['equipped_items']) 
        : null;
    
    $inventory = isset($input['inventory']) && is_array($input['inventory']) 
        ? json_encode($input['inventory']) 
        : json_encode([]);
    
    $unlockedAbilities = isset($input['unlocked_abilities']) && is_array($input['unlocked_abilities']) 
        ? json_encode($input['unlocked_abilities']) 
        : json_encode([]);
    
    $classHistory = isset($input['class_history']) && is_array($input['class_history'])
        ? json_encode($input['class_history'])
        : json_encode([]);
    
    $classHistoryWithLevels = isset($input['class_history_with_levels']) && is_array($input['class_history_with_levels'])
        ? json_encode($input['class_history_with_levels'])
        : json_encode([]);
    
    $professionHistoryWithLevels = isset($input['profession_history_with_levels']) && is_array($input['profession_history_with_levels'])
        ? json_encode($input['profession_history_with_levels'])
        : json_encode([]);
    
    $history = isset($input['history']) && is_array($input['history'])
        ? json_encode($input['history'])
        : json_encode([]);
    
    $sql = "INSERT INTO litrpg_characters (
                slug, name, description, level, xp_current, xp_to_level,
                class_id, class_activated_at_level, stats, hp_max, hp_current, ep_max, ep_current,
                credits, equipped_items, inventory, unlocked_abilities,
                portrait_image, header_image_url, status, sort_order,
                profession_name, profession_activated_at_level, highest_tier_achieved,
                class_history, class_history_with_levels, profession_history_with_levels, history
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $slug,
        $input['name'],
        $input['description'] ?? null,
        $input['level'] ?? 1,
        $input['xp_current'] ?? 0,
        $input['xp_to_level'] ?? 100,
        $input['class_id'] ?? null,
        $input['class_activated_at_level'] ?? 1,
        $stats,
        $input['hp_max'] ?? 100,
        $input['hp_current'] ?? 100,
        $input['ep_max'] ?? 50,
        $input['ep_current'] ?? 50,
        $input['credits'] ?? 0,
        $equippedItems,
        $inventory,
        $unlockedAbilities,
        $input['portrait_image'] ?? null,
        $input['header_image_url'] ?? null,
        $input['sort_order'] ?? 0,
        $input['profession_name'] ?? null,
        $input['profession_activated_at_level'] ?? null,
        $input['highest_tier_achieved'] ?? 1,
        $classHistory,
        $classHistoryWithLevels,
        $professionHistoryWithLevels,
        $history
    ]);
    
    $newId = $pdo->lastInsertId();
    
    // Return created character
    $stmt = $pdo->prepare("SELECT * FROM litrpg_characters WHERE id = ?");
    $stmt->execute([$newId]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Parse JSON fields for response
    $jsonFieldsToParse = [
        'stats', 'equipped_items', 'inventory', 'unlocked_abilities',
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
        'message' => 'Character created successfully'
    ]);

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Character with this slug already exists']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
