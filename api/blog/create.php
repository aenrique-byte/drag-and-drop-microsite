<?php
/**
 * Blog Post Create API
 * 
 * POST /api/blog/create.php
 * 
 * Request Body (JSON):
 * - title: string (required)
 * - slug: string (optional, auto-generated from title if not provided)
 * - excerpt: string (optional)
 * - content_json: string (required, TipTap JSON)
 * - content_html: string (required, sanitized HTML)
 * - cover_image: string (optional)
 * - featured_image_id: int (optional)
 * - instagram_image: string (optional)
 * - twitter_image: string (optional)
 * - facebook_image: string (optional)
 * - tags: array (optional)
 * - categories: array (optional)
 * - universe_tag: string (optional)
 * - status: string (draft|published|scheduled, default: draft)
 * - scheduled_at: datetime (required if status is scheduled)
 * - og_title: string (optional)
 * - og_description: string (optional)
 * - meta_description: string (optional)
 * - primary_keywords: string (optional)
 * - longtail_keywords: string (optional)
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');

// Only allow POST requests
require_method(['POST']);

// Require authentication
requireAuth();

// Rate limit: 10 requests/minute (authenticated users)
requireRateLimit('blog:create', 10, 60, $_SESSION['user_id'], true);

try {
    $pdo = db();
    $input = body_json();
    
    // Validate required fields
    if (empty($input['title'])) {
        json_error('Title is required', 400);
    }
    
    if (empty($input['content_json'])) {
        json_error('Content JSON is required', 400);
    }
    
    if (empty($input['content_html'])) {
        json_error('Content HTML is required', 400);
    }
    
    // Validate TipTap JSON structure
    $contentJson = $input['content_json'];
    $decoded = json_decode($contentJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_error('Invalid content JSON format', 400);
    }
    if (!isset($decoded['type']) || $decoded['type'] !== 'doc') {
        json_error('Invalid TipTap JSON structure (expected doc type)', 400);
    }
    
    // Sanitize HTML (allow safe tags only)
    $contentHtml = sanitizeHtmlContent($input['content_html']);
    
    // Generate or validate slug
    $slug = !empty($input['slug']) 
        ? sanitizeSlug($input['slug']) 
        : sanitizeSlug($input['title']);
    
    // Ensure unique slug
    $slug = generateUniqueSlug($pdo, $slug);
    
    // Calculate reading time (average 200 words per minute)
    $wordCount = str_word_count(strip_tags($contentHtml));
    $readingTime = max(1, ceil($wordCount / 200));
    
    // Validate status
    $validStatuses = ['draft', 'published', 'scheduled'];
    $status = isset($input['status']) && in_array($input['status'], $validStatuses) 
        ? $input['status'] 
        : 'draft';
    
    // If published, set published_at to now
    $publishedAt = null;
    if ($status === 'published') {
        $publishedAt = date('Y-m-d H:i:s');
    }
    
    // Validate scheduled_at if status is scheduled
    $scheduledAt = null;
    if ($status === 'scheduled') {
        if (empty($input['scheduled_at'])) {
            json_error('Scheduled date is required for scheduled posts', 400);
        }
        $scheduledAt = date('Y-m-d H:i:s', strtotime($input['scheduled_at']));
        if (strtotime($scheduledAt) <= time()) {
            json_error('Scheduled date must be in the future', 400);
        }
    }
    
    // Process tags and categories as JSON
    $tags = isset($input['tags']) && is_array($input['tags']) 
        ? json_encode(array_values(array_filter($input['tags'])))
        : null;
    
    $categories = isset($input['categories']) && is_array($input['categories'])
        ? json_encode(array_values(array_filter($input['categories'])))
        : null;
    
    // Insert blog post
    $stmt = $pdo->prepare("
        INSERT INTO blog_posts (
            slug, title, excerpt, content_json, content_html,
            cover_image, featured_image_id,
            instagram_image, instagram_image_id,
            twitter_image, twitter_image_id,
            facebook_image, facebook_image_id,
            og_title, og_description,
            meta_description, primary_keywords, longtail_keywords,
            tags, categories, universe_tag,
            author_id, status, published_at, scheduled_at,
            reading_time, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, NOW(), NOW()
        )
    ");
    
    $stmt->execute([
        $slug,
        $input['title'],
        $input['excerpt'] ?? null,
        $contentJson,
        $contentHtml,
        $input['cover_image'] ?? null,
        $input['featured_image_id'] ?? null,
        $input['instagram_image'] ?? null,
        $input['instagram_image_id'] ?? null,
        $input['twitter_image'] ?? null,
        $input['twitter_image_id'] ?? null,
        $input['facebook_image'] ?? null,
        $input['facebook_image_id'] ?? null,
        $input['og_title'] ?? null,
        $input['og_description'] ?? null,
        $input['meta_description'] ?? null,
        $input['primary_keywords'] ?? null,
        $input['longtail_keywords'] ?? null,
        $tags,
        $categories,
        $input['universe_tag'] ?? null,
        $_SESSION['user_id'],
        $status,
        $publishedAt,
        $scheduledAt,
        $readingTime
    ]);
    
    $postId = $pdo->lastInsertId();
    
    // Create initial revision
    $revisionStmt = $pdo->prepare("
        INSERT INTO blog_revisions (
            post_id, content_json, content_html, title, excerpt,
            edited_by, change_summary, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'Initial creation', NOW())
    ");
    
    $revisionStmt->execute([
        $postId,
        $contentJson,
        $contentHtml,
        $input['title'],
        $input['excerpt'] ?? null,
        $_SESSION['user_id']
    ]);
    
    // Fetch the created post
    $fetchStmt = $pdo->prepare("
        SELECT bp.*, u.username as author_name
        FROM blog_posts bp
        LEFT JOIN users u ON bp.author_id = u.id
        WHERE bp.id = ?
    ");
    $fetchStmt->execute([$postId]);
    $post = $fetchStmt->fetch();
    
    // Process JSON fields for response
    $post['tags'] = !empty($post['tags']) ? json_decode($post['tags'], true) : [];
    $post['categories'] = !empty($post['categories']) ? json_decode($post['categories'], true) : [];
    
    json_response([
        'success' => true,
        'post' => $post,
        'id' => $postId
    ], 201);

} catch (Exception $e) {
    error_log("Blog create error: " . $e->getMessage());
    json_error('Failed to create blog post', 500);
}

/**
 * Sanitize slug to URL-friendly format
 */
function sanitizeSlug($title) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    
    // Limit length
    if (strlen($slug) > 200) {
        $slug = substr($slug, 0, 200);
        $slug = substr($slug, 0, strrpos($slug, '-') ?: 200);
    }
    
    return $slug ?: 'untitled';
}

/**
 * Generate unique slug by appending number if collision exists
 */
function generateUniqueSlug($pdo, $baseSlug, $postId = null) {
    $slug = $baseSlug;
    $counter = 1;
    
    while (true) {
        $stmt = $pdo->prepare("
            SELECT id FROM blog_posts
            WHERE slug = ? AND (? IS NULL OR id != ?)
        ");
        $stmt->execute([$slug, $postId, $postId]);
        
        if (!$stmt->fetch()) {
            return $slug;
        }
        
        $slug = $baseSlug . '-' . $counter;
        $counter++;
        
        // Safety limit
        if ($counter > 100) {
            $slug = $baseSlug . '-' . uniqid();
            break;
        }
    }
    
    return $slug;
}

/**
 * Sanitize HTML content - allow only safe tags
 */
function sanitizeHtmlContent($html) {
    // Allow common formatting tags
    $allowedTags = '<p><br><strong><b><em><i><u><s><strike><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code><img><figure><figcaption><hr><table><thead><tbody><tr><th><td><span><div>';
    
    // Strip dangerous tags
    $html = strip_tags($html, $allowedTags);
    
    // Remove javascript: URLs and event handlers
    $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    $html = preg_replace('/javascript:/i', '', $html);
    
    return $html;
}
