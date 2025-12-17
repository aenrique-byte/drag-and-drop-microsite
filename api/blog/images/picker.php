<?php
/**
 * Blog Image Picker API
 * 
 * Lists images from galleries for the blog image picker.
 * Supports filtering by dimensions, aspect ratio, and search.
 * 
 * GET /api/blog/images/picker.php
 * 
 * Query params:
 *   - page: Page number (default 1)
 *   - limit: Items per page (default 24, max 100)
 *   - q: Search query (searches title, prompt, checkpoint)
 *   - gallery_id: Filter by specific gallery ID
 *   - min_width: Minimum width in pixels
 *   - min_height: Minimum height in pixels
 *   - aspect_ratio: Filter by aspect ratio ('square', 'landscape', 'portrait')
 *   - source: 'blog-assets' (blog gallery only) or 'all' (all galleries)
 * 
 * Response:
 *   - success: boolean
 *   - images: array of image objects
 *   - galleries: array of available galleries
 *   - total: total count
 *   - page: current page
 *   - limit: items per page
 *   - pages: total pages
 */

declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');

require_method(['GET']);

// Rate limit: 60 requests per minute
requireRateLimit('blog:images:picker', 60, 60);

// Auth required for admin features
requireAuth();

// Parse parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 24)));
$offset = ($page - 1) * $limit;
$search = trim((string)($_GET['q'] ?? ''));
$galleryId = isset($_GET['gallery_id']) && $_GET['gallery_id'] !== '' ? intval($_GET['gallery_id']) : null;
$minWidth = isset($_GET['min_width']) && $_GET['min_width'] !== '' ? intval($_GET['min_width']) : null;
$minHeight = isset($_GET['min_height']) && $_GET['min_height'] !== '' ? intval($_GET['min_height']) : null;
$aspectRatio = trim(strtolower((string)($_GET['aspect_ratio'] ?? '')));
$source = trim(strtolower((string)($_GET['source'] ?? 'all')));

try {
    $pdo = db();
    
    // Build query conditions
    $conditions = [];
    $params = [];
    
    // Source filter: blog-assets only or all galleries
    if ($source === 'blog-assets') {
        // Get blog-assets gallery ID
        $stmt = $pdo->prepare("SELECT id FROM galleries WHERE slug = 'blog-assets' LIMIT 1");
        $stmt->execute();
        $blogGallery = $stmt->fetch();
        if ($blogGallery) {
            $conditions[] = "i.gallery_id = ?";
            $params[] = $blogGallery['id'];
        }
    } elseif ($galleryId !== null) {
        $conditions[] = "i.gallery_id = ?";
        $params[] = $galleryId;
    } else {
        // All published galleries
        $conditions[] = "g.status = 'published'";
    }
    
    // Search filter
    if ($search !== '') {
        $conditions[] = "(i.title LIKE ? OR i.prompt LIKE ? OR i.checkpoint LIKE ? OR i.filename LIKE ?)";
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    
    // Dimension filters
    if ($minWidth !== null && $minWidth > 0) {
        $conditions[] = "i.width >= ?";
        $params[] = $minWidth;
    }
    
    if ($minHeight !== null && $minHeight > 0) {
        $conditions[] = "i.height >= ?";
        $params[] = $minHeight;
    }
    
    // Aspect ratio filter using stored aspect_ratio column
    if ($aspectRatio !== '' && in_array($aspectRatio, ['square', 'landscape', 'portrait'], true)) {
        switch ($aspectRatio) {
            case 'square':
                // Aspect ratio between 0.95 and 1.05
                $conditions[] = "(i.aspect_ratio >= 0.95 AND i.aspect_ratio <= 1.05)";
                break;
            case 'landscape':
                // Aspect ratio > 1.0
                $conditions[] = "i.aspect_ratio > 1.05";
                break;
            case 'portrait':
                // Aspect ratio < 1.0
                $conditions[] = "i.aspect_ratio < 0.95";
                break;
        }
    }
    
    // Only images (not videos)
    $conditions[] = "(i.media_type IS NULL OR i.media_type = 'image')";
    
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Get total count
    $countSql = "
        SELECT COUNT(*) AS total
        FROM images i
        JOIN galleries g ON i.gallery_id = g.id
        $where
    ";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetch()['total'];
    
    // Get images with gallery info
    $sql = "
        SELECT 
            i.id,
            i.title,
            i.filename,
            i.original_path,
            i.thumbnail_path,
            i.width,
            i.height,
            i.aspect_ratio,
            i.file_size,
            i.mime_type,
            i.prompt,
            i.checkpoint,
            i.gallery_id,
            i.created_at,
            g.slug AS gallery_slug,
            g.title AS gallery_title
        FROM images i
        JOIN galleries g ON i.gallery_id = g.id
        $where
        ORDER BY i.created_at DESC, i.id DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    
    // Format images
    $images = array_map(function($row) {
        $originalPath = $row['original_path'];
        $thumbnailPath = $row['thumbnail_path'];
        
        // Ensure paths start with /api/uploads/
        if ($originalPath && strpos($originalPath, '/uploads/') === 0) {
            $originalPath = '/api' . $originalPath;
        } elseif ($originalPath && $originalPath[0] !== '/') {
            $originalPath = '/api/uploads/' . ltrim($originalPath, '/');
        }
        
        if ($thumbnailPath && strpos($thumbnailPath, '/uploads/') === 0) {
            $thumbnailPath = '/api' . $thumbnailPath;
        } elseif ($thumbnailPath && $thumbnailPath[0] !== '/') {
            $thumbnailPath = '/api/uploads/' . ltrim($thumbnailPath, '/');
        }
        
        return [
            'id' => (int)$row['id'],
            'title' => $row['title'] ?: $row['filename'],
            'original_path' => $originalPath,
            'thumbnail_path' => $thumbnailPath,
            'width' => $row['width'] !== null ? (int)$row['width'] : null,
            'height' => $row['height'] !== null ? (int)$row['height'] : null,
            'aspect_ratio' => $row['aspect_ratio'] !== null ? (float)$row['aspect_ratio'] : null,
            'file_size' => $row['file_size'] !== null ? (int)$row['file_size'] : null,
            'mime_type' => $row['mime_type'],
            'prompt' => $row['prompt'],
            'checkpoint' => $row['checkpoint'],
            'gallery_id' => (int)$row['gallery_id'],
            'gallery_slug' => $row['gallery_slug'],
            'gallery_title' => $row['gallery_title'],
            'created_at' => $row['created_at'],
        ];
    }, $rows);
    
    // Get list of available galleries for dropdown
    $gallerySql = "
        SELECT id, slug, title, 
               (SELECT COUNT(*) FROM images WHERE gallery_id = galleries.id AND (media_type IS NULL OR media_type = 'image')) AS image_count
        FROM galleries 
        WHERE status = 'published'
        ORDER BY 
            CASE WHEN slug = 'blog-assets' THEN 0 ELSE 1 END,
            title ASC
    ";
    $stmt = $pdo->query($gallerySql);
    $galleries = array_map(function($row) {
        return [
            'id' => (int)$row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'image_count' => (int)$row['image_count'],
        ];
    }, $stmt->fetchAll());
    
    json_response([
        'success' => true,
        'images' => $images,
        'galleries' => $galleries,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => $total > 0 ? ceil($total / $limit) : 0,
    ]);
    
} catch (Throwable $e) {
    error_log("Blog image picker error: " . $e->getMessage());
    json_error('Failed to list images', 500, ['detail' => $e->getMessage()]);
}
