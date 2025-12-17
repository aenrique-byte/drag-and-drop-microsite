<?php
/**
 * List all LitRPG characters
 * GET /api/litrpg/characters/list.php
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT c.id, c.slug, c.name, c.description, c.level, c.xp_current, c.xp_to_level,
                   c.class_id, c.stats, c.base_stats, c.unspent_attribute_points,
                   c.hp_max, c.hp_current, c.ep_max, c.ep_current,
                   c.credits, c.equipped_items, c.inventory, c.unlocked_abilities,
                   c.portrait_image, c.status, c.sort_order,
                   c.class_history, c.class_history_with_levels, c.class_activated_at_level,
                   c.profession_name, c.profession_activated_at_level, c.profession_history_with_levels,
                   c.highest_tier_achieved, c.header_image_url, c.history
            FROM litrpg_characters c
            WHERE c.status = 'active'
            ORDER BY c.sort_order ASC, c.name ASC";
    
    $stmt = $pdo->query($sql);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse JSON fields
    $jsonFieldsToParse = [
        'stats', 'base_stats', 'equipped_items', 'inventory', 'unlocked_abilities',
        'class_history', 'class_history_with_levels', 
        'profession_history_with_levels', 'history'
    ];
    
    foreach ($characters as &$char) {
        foreach ($jsonFieldsToParse as $field) {
            if (isset($char[$field]) && $char[$field]) {
                $char[$field] = json_decode($char[$field], true);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'characters' => $characters,
        'count' => count($characters)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
