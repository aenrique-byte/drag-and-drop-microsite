<?php
declare(strict_types=1);
// Load bootstrap from parent directory with absolute path
$bootstrapPath = dirname(__DIR__) . '/bootstrap.php';
require_once $bootstrapPath;

// GET /api/shoutouts/shoutouts.php - Get shoutout templates (optionally filtered by storyId)
// POST /api/shoutouts/shoutouts.php - Create or update shoutout template (admin only)
// DELETE /api/shoutouts/shoutouts.php?id=xxx - Delete shoutout template (admin only)

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $pdo = db();
        $storyId = $_GET['storyId'] ?? null;
        
        if ($storyId) {
            // Get shoutouts for specific story (including global ones)
            $stmt = $pdo->prepare("
                SELECT * FROM shoutout_admin_shoutouts 
                WHERE story_id IS NULL OR story_id = ? 
                ORDER BY created_at ASC
            ");
            $stmt->execute([$storyId]);
        } else {
            // Get all shoutouts
            $stmt = $pdo->query("SELECT * FROM shoutout_admin_shoutouts ORDER BY created_at ASC");
        }
        
        $shoutouts = $stmt->fetchAll();
        
        // Convert to frontend format (camelCase)
        $result = array_map(function($shoutout) {
            return [
                'id' => $shoutout['id'],
                'label' => $shoutout['label'],
                'code' => $shoutout['code'],
                'storyId' => $shoutout['story_id']
            ];
        }, $shoutouts);
        
        json_response($result);
        
    } catch (Throwable $e) {
        json_error('Failed to fetch shoutouts.', 500, ['detail' => $e->getMessage()]);
    }
}

if ($method === 'POST') {
    require_auth(); // Admin only
    
    try {
        $pdo = db();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || !isset($input['label']) || !isset($input['code'])) {
            json_error('Missing required fields', 400);
        }
        
        $id = $input['id'];
        $label = $input['label'];
        $code = $input['code'];
        $storyId = $input['storyId'] ?? null;
        
        // Check if shoutout exists
        $stmt = $pdo->prepare("SELECT id FROM shoutout_admin_shoutouts WHERE id = ?");
        $stmt->execute([$id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing shoutout
            $stmt = $pdo->prepare("
                UPDATE shoutout_admin_shoutouts 
                SET label = ?, code = ?, story_id = ? 
                WHERE id = ?
            ");
            $stmt->execute([$label, $code, $storyId, $id]);
        } else {
            // Insert new shoutout
            $stmt = $pdo->prepare("
                INSERT INTO shoutout_admin_shoutouts (id, label, code, story_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$id, $label, $code, $storyId]);
        }
        
        json_response(['id' => $id, 'message' => 'Shoutout template saved successfully']);
        
    } catch (Throwable $e) {
        json_error('Failed to save shoutout.', 500, ['detail' => $e->getMessage()]);
    }
}

if ($method === 'DELETE') {
    require_auth(); // Admin only
    
    try {
        $pdo = db();
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            json_error('Shoutout ID required', 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM shoutout_admin_shoutouts WHERE id = ?");
        $stmt->execute([$id]);
        
        json_response(['message' => 'Shoutout template deleted successfully']);
        
    } catch (Throwable $e) {
        json_error('Failed to delete shoutout.', 500, ['detail' => $e->getMessage()]);
    }
}

json_error('Method not allowed', 405);
