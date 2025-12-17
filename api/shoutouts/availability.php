<?php
declare(strict_types=1);
// Load bootstrap from parent directory with absolute path
$bootstrapPath = dirname(__DIR__) . '/bootstrap.php';
require_once $bootstrapPath;

// GET /api/shoutouts/availability.php?storyId=xxx - Get availability for a story
// POST /api/shoutouts/availability.php - Set availability for a date (admin only)

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $pdo = db();
        $storyId = $_GET['storyId'] ?? null;
        
        if (!$storyId) {
            json_error('Story ID required', 400);
        }
        
        $stmt = $pdo->prepare("
            SELECT date_str 
            FROM shoutout_availability 
            WHERE story_id = ? 
            ORDER BY date_str ASC
        ");
        $stmt->execute([$storyId]);
        $rows = $stmt->fetchAll();
        
        // Return array of date strings in YYYY-MM-DD format
        $dates = array_map(function($row) {
            return $row['date_str'];
        }, $rows);
        
        json_response($dates);
        
    } catch (Throwable $e) {
        json_error('Failed to fetch availability.', 500, ['detail' => $e->getMessage()]);
    }
}

if ($method === 'POST') {
    require_auth(); // Admin only
    
    try {
        $pdo = db();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['storyId']) || !isset($input['dateStr']) || !isset($input['isAvailable'])) {
            json_error('Missing required fields', 400);
        }
        
        $storyId = $input['storyId'];
        $dateStr = $input['dateStr'];
        $isAvailable = $input['isAvailable'];
        
        if ($isAvailable) {
            // Add availability (INSERT IGNORE to avoid duplicates)
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO shoutout_availability (date_str, story_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$dateStr, $storyId]);
        } else {
            // Remove availability
            $stmt = $pdo->prepare("
                DELETE FROM shoutout_availability 
                WHERE date_str = ? AND story_id = ?
            ");
            $stmt->execute([$dateStr, $storyId]);
        }
        
        json_response(['message' => 'Availability updated successfully']);
        
    } catch (Throwable $e) {
        json_error('Failed to update availability.', 500, ['detail' => $e->getMessage()]);
    }
}

json_error('Method not allowed', 405);
