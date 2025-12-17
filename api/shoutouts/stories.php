<?php
declare(strict_types=1);
$bootstrapPath = dirname(__DIR__) . '/bootstrap.php';
require_once $bootstrapPath;

// GET /api/shoutouts/stories.php - Get all stories or single story
// POST /api/shoutouts/stories.php - Create or update story (admin only)
// DELETE /api/shoutouts/stories.php?id=xxx - Delete story (admin only)

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $pdo = db();
        $id = $_GET['id'] ?? null;
        
        if ($id) {
            // Get single story
            $stmt = $pdo->prepare("SELECT * FROM shoutout_stories WHERE id = ?");
            $stmt->execute([$id]);
            $story = $stmt->fetch();
            
            if (!$story) {
                json_error('Story not found', 404);
            }
            
            // Convert to camelCase for frontend
            $result = [
                'id' => $story['id'],
                'title' => $story['title'],
                'link' => $story['link'],
                'coverImage' => $story['cover_image'],
                'color' => $story['color'],
                'created_at' => $story['created_at'],
                'updated_at' => $story['updated_at']
            ];
            
            json_response($result);
        } else {
            // Get all stories
            $stmt = $pdo->query("SELECT * FROM shoutout_stories ORDER BY created_at ASC");
            $stories = $stmt->fetchAll();
            
            // Convert to camelCase for frontend
            $result = array_map(function($story) {
                return [
                    'id' => $story['id'],
                    'title' => $story['title'],
                    'link' => $story['link'],
                    'coverImage' => $story['cover_image'],
                    'color' => $story['color'],
                    'created_at' => $story['created_at'],
                    'updated_at' => $story['updated_at']
                ];
            }, $stories);
            
            json_response($result);
        }
    } catch (Throwable $e) {
        json_error('Failed to fetch stories.', 500, ['detail' => $e->getMessage()]);
    }
}

if ($method === 'POST') {
    require_auth(); // Admin only
    
    try {
        $pdo = db();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['id']) || !isset($input['title']) || !isset($input['link'])) {
            json_error('Missing required fields', 400);
        }
        
        $id = $input['id'];
        $title = $input['title'];
        $link = $input['link'];
        $coverImage = $input['coverImage'] ?? 'https://picsum.photos/400/600';
        $color = $input['color'] ?? 'amber';
        
        // Check if story exists
        $stmt = $pdo->prepare("SELECT id FROM shoutout_stories WHERE id = ?");
        $stmt->execute([$id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing story
            $stmt = $pdo->prepare("
                UPDATE shoutout_stories 
                SET title = ?, link = ?, cover_image = ?, color = ? 
                WHERE id = ?
            ");
            $stmt->execute([$title, $link, $coverImage, $color, $id]);
        } else {
            // Insert new story
            $stmt = $pdo->prepare("
                INSERT INTO shoutout_stories (id, title, link, cover_image, color) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id, $title, $link, $coverImage, $color]);
        }
        
        json_response(['id' => $id, 'message' => 'Story saved successfully']);
        
    } catch (Throwable $e) {
        json_error('Failed to save story.', 500, ['detail' => $e->getMessage()]);
    }
}

if ($method === 'DELETE') {
    require_auth(); // Admin only
    
    try {
        $pdo = db();
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            json_error('Story ID required', 400);
        }
        
        // Check if this is the last story
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM shoutout_stories");
        $count = (int)$stmt->fetch()['count'];
        
        if ($count <= 1) {
            json_error('Cannot delete the last story', 400);
        }
        
        // Delete story (CASCADE will handle related records)
        $stmt = $pdo->prepare("DELETE FROM shoutout_stories WHERE id = ?");
        $stmt->execute([$id]);
        
        json_response(['message' => 'Story deleted successfully']);
        
    } catch (Throwable $e) {
        json_error('Failed to delete story.', 500, ['detail' => $e->getMessage()]);
    }
}

json_error('Method not allowed', 405);
