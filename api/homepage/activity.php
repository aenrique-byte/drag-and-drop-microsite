<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET - List all activity items
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM activity_feed ORDER BY published_at DESC");
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($activities as &$item) {
            $item['id'] = (int)$item['id'];
            $item['is_active'] = (bool)$item['is_active'];
        }
        
        jsonResponse(['success' => true, 'activities' => $activities]);
    } catch (Exception $e) {
        error_log("Activity GET error: " . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Failed to fetch activities'], 500);
    }
    exit;
}

// POST - Create new activity
if ($method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['title'])) {
            jsonResponse(['success' => false, 'error' => 'Title is required'], 400);
        }
        if (empty($data['source'])) {
            jsonResponse(['success' => false, 'error' => 'Source is required'], 400);
        }
        
        $stmt = $pdo->prepare("INSERT INTO activity_feed 
            (type, source, label, title, series_title, url, published_at, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['type'] ?? 'misc',
            $data['source'],
            $data['label'] ?? '',
            $data['title'],
            $data['series_title'] ?? '',
            $data['url'] ?? '',
            $data['published_at'] ?? date('Y-m-d H:i:s'),
            isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1
        ]);
        
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Activity created']);
    } catch (Exception $e) {
        error_log("Activity POST error: " . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Failed to create activity'], 500);
    }
    exit;
}

// PUT - Update activity
if ($method === 'PUT') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            jsonResponse(['success' => false, 'error' => 'Activity ID is required'], 400);
        }
        if (empty($data['title'])) {
            jsonResponse(['success' => false, 'error' => 'Title is required'], 400);
        }
        if (empty($data['source'])) {
            jsonResponse(['success' => false, 'error' => 'Source is required'], 400);
        }
        
        $stmt = $pdo->prepare("UPDATE activity_feed SET 
            type = ?, source = ?, label = ?, title = ?, series_title = ?, 
            url = ?, published_at = ?, is_active = ?
            WHERE id = ?");
        $stmt->execute([
            $data['type'] ?? 'misc',
            $data['source'],
            $data['label'] ?? '',
            $data['title'],
            $data['series_title'] ?? '',
            $data['url'] ?? '',
            $data['published_at'] ?? date('Y-m-d H:i:s'),
            isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1,
            (int)$data['id']
        ]);
        
        jsonResponse(['success' => true, 'message' => 'Activity updated']);
    } catch (Exception $e) {
        error_log("Activity PUT error: " . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Failed to update activity'], 500);
    }
    exit;
}

// DELETE - Delete activity
if ($method === 'DELETE') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            jsonResponse(['success' => false, 'error' => 'Activity ID is required'], 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM activity_feed WHERE id = ?");
        $stmt->execute([(int)$data['id']]);
        
        jsonResponse(['success' => true, 'message' => 'Activity deleted']);
    } catch (Exception $e) {
        error_log("Activity DELETE error: " . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Failed to delete activity'], 500);
    }
    exit;
}

jsonResponse(['error' => 'Method not allowed'], 405);
