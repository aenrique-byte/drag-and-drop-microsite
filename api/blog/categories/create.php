<?php
/**
 * Blog Category Create API
 * 
 * POST /api/blog/categories/create.php
 * 
 * Request Body (JSON):
 * - name: string (required)
 * - description: string (optional)
 * - sort_order: int (optional, default 0)
 */

require_once '../../bootstrap.php';

header('Content-Type: application/json');

// Only allow POST requests
require_method(['POST']);

// Require authentication
requireAuth();

// Rate limit: 30 requests/minute
requireRateLimit('blog:categories:create', 30, 60, $_SESSION['user_id'], true);

try {
    $pdo = db();
    $input = body_json();
    
    // Validate name
    if (empty($input['name'])) {
        json_error('Category name is required', 400);
    }
    
    $name = trim($input['name']);
    
    if (strlen($name) > 100) {
        json_error('Category name must be 100 characters or less', 400);
    }
    
    // Generate slug from name
    $slug = strtolower($name);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    if (empty($slug)) {
        $slug = 'category-' . time();
    }
    
    // Check if name or slug already exists
    $checkStmt = $pdo->prepare("SELECT id FROM blog_categories WHERE name = ? OR slug = ?");
    $checkStmt->execute([$name, $slug]);
    
    if ($checkStmt->fetch()) {
        json_error('A category with this name already exists', 400);
    }
    
    // Insert category
    $stmt = $pdo->prepare("
        INSERT INTO blog_categories (slug, name, description, sort_order, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $slug,
        $name,
        $input['description'] ?? null,
        intval($input['sort_order'] ?? 0)
    ]);
    
    $categoryId = $pdo->lastInsertId();
    
    // Fetch and return the new category
    $fetchStmt = $pdo->prepare("SELECT * FROM blog_categories WHERE id = ?");
    $fetchStmt->execute([$categoryId]);
    $category = $fetchStmt->fetch();
    
    json_response([
        'success' => true,
        'category' => $category
    ]);

} catch (Exception $e) {
    error_log("Blog category create error: " . $e->getMessage());
    json_error('Failed to create category', 500);
}
