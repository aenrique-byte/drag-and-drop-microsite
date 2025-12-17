<?php
/**
 * Get a single LitRPG character by slug or ID
 * GET /api/litrpg/characters/get.php?slug=character-slug
 * GET /api/litrpg/characters/get.php?id=1
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

try {
    $slug = $_GET['slug'] ?? null;
    $id = $_GET['id'] ?? null;
    
    if (!$slug && !$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'slug or id parameter required']);
        exit;
    }
    
    $sql = "SELECT c.*
            FROM litrpg_characters c
            WHERE " . ($slug ? "c.slug = ?" : "c.id = ?");
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$slug ?? $id]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$character) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Character not found']);
        exit;
    }
    
    // Parse JSON fields
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
        'character' => $character
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
