<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET - List all tools
if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM homepage_tools ORDER BY display_order ASC, id ASC");
        $tools = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tools as &$tool) {
            $tool['id'] = (int)$tool['id'];
            $tool['display_order'] = (int)$tool['display_order'];
            $tool['is_active'] = (bool)$tool['is_active'];
        }
        
        jsonResponse(['success' => true, 'tools' => $tools]);
    } catch (Exception $e) {
        error_log("Tools GET error: " . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Failed to fetch tools'], 500);
    }
    exit;
}

// POST - Create new tool
if ($method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['title'])) {
            jsonResponse(['success' => false, 'error' => 'Title is required'], 400);
        }
        
        $stmt = $pdo->prepare("INSERT INTO homepage_tools 
            (title, description, icon, link, display_order, is_active) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['icon'] ?? '🔧',
            $data['link'] ?? '',
            $data['display_order'] ?? 0,
            isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1
        ]);
        
        jsonResponse(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Tool created']);
    } catch (Exception $e) {
        error_log("Tools POST error: " . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Failed to create tool'], 500);
    }
    exit;
}

// PUT - Update tool
if ($method === 'PUT') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            jsonResponse(['success' => false, 'error' => 'Tool ID is required'], 400);
        }
        if (empty($data['title'])) {
            jsonResponse(['success' => false, 'error' => 'Title is required'], 400);
        }
        
        $stmt = $pdo->prepare("UPDATE homepage_tools SET 
            title = ?, description = ?, icon = ?, link = ?, display_order = ?, is_active = ?
            WHERE id = ?");
        $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['icon'] ?? '🔧',
            $data['link'] ?? '',
            $data['display_order'] ?? 0,
            isset($data['is_active']) ? ($data['is_active'] ? 1 : 0) : 1,
            (int)$data['id']
        ]);
        
        jsonResponse(['success' => true, 'message' => 'Tool updated']);
    } catch (Exception $e) {
        error_log("Tools PUT error: " . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Failed to update tool'], 500);
    }
    exit;
}

// DELETE - Delete tool
if ($method === 'DELETE') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            jsonResponse(['success' => false, 'error' => 'Tool ID is required'], 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM homepage_tools WHERE id = ?");
        $stmt->execute([(int)$data['id']]);
        
        jsonResponse(['success' => true, 'message' => 'Tool deleted']);
    } catch (Exception $e) {
        error_log("Tools DELETE error: " . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Failed to delete tool'], 500);
    }
    exit;
}

jsonResponse(['error' => 'Method not allowed'], 405);
