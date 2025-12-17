<?php
/**
 * Blog Image Link API
 * 
 * Tracks gallery images used in blog posts for:
 * - Orphan cleanup
 * - Usage analytics
 * - Cross-linking features
 * 
 * POST /api/blog/images/link.php
 * 
 * Request body:
 *   - blog_post_id: int (required)
 *   - image_id: int (required)
 *   - source: string (required) - 'inline', 'featured', 'social_instagram', 'social_twitter', 'social_facebook'
 *   - position_order: int (optional, for inline images)
 * 
 * DELETE /api/blog/images/link.php
 * 
 * Request body:
 *   - blog_post_id: int (required)
 *   - image_id: int (required)
 *   - source: string (required)
 * 
 * GET /api/blog/images/link.php?blog_post_id=123
 * 
 * Lists all linked images for a blog post
 */

declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');

require_method(['GET', 'POST', 'DELETE']);

// Rate limit: 30 requests per minute
requireRateLimit('blog:images:link', 30, 60);

// Auth required
requireAuth();

try {
    $pdo = db();
    
    // Handle GET request - list linked images
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $blogPostId = intval($_GET['blog_post_id'] ?? 0);
        if ($blogPostId <= 0) {
            json_error('blog_post_id is required', 400);
        }
        
        // Verify post exists
        $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE id = ?");
        $stmt->execute([$blogPostId]);
        if (!$stmt->fetch()) {
            json_error('Blog post not found', 404);
        }
        
        // Get linked images with details
        $sql = "
            SELECT 
                bci.id AS link_id,
                bci.source,
                bci.position_order,
                bci.created_at AS linked_at,
                i.id AS image_id,
                i.title,
                i.filename,
                i.original_path,
                i.thumbnail_path,
                i.width,
                i.height,
                i.prompt,
                i.checkpoint,
                g.slug AS gallery_slug,
                g.title AS gallery_title
            FROM blog_content_images bci
            JOIN images i ON bci.image_id = i.id
            JOIN galleries g ON i.gallery_id = g.id
            WHERE bci.blog_post_id = ?
            ORDER BY bci.source, bci.position_order
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$blogPostId]);
        $rows = $stmt->fetchAll();
        
        $images = array_map(function($row) {
            $originalPath = $row['original_path'];
            $thumbnailPath = $row['thumbnail_path'];
            
            // Ensure paths start with /api/uploads/
            if ($originalPath && strpos($originalPath, '/uploads/') === 0) {
                $originalPath = '/api' . $originalPath;
            }
            if ($thumbnailPath && strpos($thumbnailPath, '/uploads/') === 0) {
                $thumbnailPath = '/api' . $thumbnailPath;
            }
            
            return [
                'link_id' => (int)$row['link_id'],
                'source' => $row['source'],
                'position_order' => (int)$row['position_order'],
                'linked_at' => $row['linked_at'],
                'image_id' => (int)$row['image_id'],
                'title' => $row['title'] ?: $row['filename'],
                'original_path' => $originalPath,
                'thumbnail_path' => $thumbnailPath,
                'width' => $row['width'] !== null ? (int)$row['width'] : null,
                'height' => $row['height'] !== null ? (int)$row['height'] : null,
                'prompt' => $row['prompt'],
                'checkpoint' => $row['checkpoint'],
                'gallery_slug' => $row['gallery_slug'],
                'gallery_title' => $row['gallery_title'],
            ];
        }, $rows);
        
        // Group by source
        $grouped = [
            'inline' => [],
            'featured' => [],
            'social_instagram' => [],
            'social_twitter' => [],
            'social_facebook' => [],
        ];
        
        foreach ($images as $img) {
            $source = $img['source'];
            if (isset($grouped[$source])) {
                $grouped[$source][] = $img;
            }
        }
        
        json_response([
            'success' => true,
            'blog_post_id' => $blogPostId,
            'images' => $images,
            'grouped' => $grouped,
            'totals' => [
                'inline' => count($grouped['inline']),
                'featured' => count($grouped['featured']),
                'social_instagram' => count($grouped['social_instagram']),
                'social_twitter' => count($grouped['social_twitter']),
                'social_facebook' => count($grouped['social_facebook']),
                'total' => count($images),
            ],
        ]);
    }
    
    // Handle POST request - create/update link
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = body_json();
        
        $blogPostId = intval($input['blog_post_id'] ?? 0);
        $imageId = intval($input['image_id'] ?? 0);
        $source = trim(strtolower((string)($input['source'] ?? '')));
        $positionOrder = intval($input['position_order'] ?? 0);
        
        // Validate required fields
        if ($blogPostId <= 0) {
            json_error('blog_post_id is required', 400);
        }
        if ($imageId <= 0) {
            json_error('image_id is required', 400);
        }
        $validSources = ['inline', 'featured', 'social_instagram', 'social_twitter', 'social_facebook'];
        if (!in_array($source, $validSources, true)) {
            json_error('source must be one of: ' . implode(', ', $validSources), 400);
        }
        
        // Verify blog post exists
        $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE id = ?");
        $stmt->execute([$blogPostId]);
        if (!$stmt->fetch()) {
            json_error('Blog post not found', 404);
        }
        
        // Verify image exists
        $stmt = $pdo->prepare("SELECT id FROM images WHERE id = ?");
        $stmt->execute([$imageId]);
        if (!$stmt->fetch()) {
            json_error('Image not found', 404);
        }
        
        // Insert or update link (upsert)
        $stmt = $pdo->prepare("
            INSERT INTO blog_content_images (blog_post_id, image_id, source, position_order)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE position_order = VALUES(position_order)
        ");
        $stmt->execute([$blogPostId, $imageId, $source, $positionOrder]);
        
        // Get the inserted/updated record
        $stmt = $pdo->prepare("
            SELECT id, blog_post_id, image_id, source, position_order, created_at
            FROM blog_content_images
            WHERE blog_post_id = ? AND image_id = ? AND source = ?
        ");
        $stmt->execute([$blogPostId, $imageId, $source]);
        $link = $stmt->fetch();
        
        json_response([
            'success' => true,
            'message' => 'Image linked successfully',
            'link' => [
                'id' => (int)$link['id'],
                'blog_post_id' => (int)$link['blog_post_id'],
                'image_id' => (int)$link['image_id'],
                'source' => $link['source'],
                'position_order' => (int)$link['position_order'],
                'created_at' => $link['created_at'],
            ],
        ]);
    }
    
    // Handle DELETE request - remove link
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $input = body_json();
        
        $blogPostId = intval($input['blog_post_id'] ?? 0);
        $imageId = intval($input['image_id'] ?? 0);
        $source = trim(strtolower((string)($input['source'] ?? '')));
        
        // Validate required fields
        if ($blogPostId <= 0) {
            json_error('blog_post_id is required', 400);
        }
        if ($imageId <= 0) {
            json_error('image_id is required', 400);
        }
        $validSources = ['inline', 'featured', 'social_instagram', 'social_twitter', 'social_facebook'];
        if (!in_array($source, $validSources, true)) {
            json_error('source must be one of: ' . implode(', ', $validSources), 400);
        }
        
        // Delete the link
        $stmt = $pdo->prepare("
            DELETE FROM blog_content_images
            WHERE blog_post_id = ? AND image_id = ? AND source = ?
        ");
        $stmt->execute([$blogPostId, $imageId, $source]);
        
        $deleted = $stmt->rowCount() > 0;
        
        json_response([
            'success' => true,
            'deleted' => $deleted,
            'message' => $deleted ? 'Image link removed' : 'Link not found',
        ]);
    }
    
} catch (Throwable $e) {
    error_log("Blog image link error: " . $e->getMessage());
    json_error('Failed to process image link', 500, ['detail' => $e->getMessage()]);
}
