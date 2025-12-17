<?php
/**
 * Blog Post Update API
 * 
 * POST /api/blog/update.php
 * 
 * Request Body (JSON):
 * - id: int (required)
 * - title: string (optional)
 * - slug: string (optional)
 * - excerpt: string (optional)
 * - content_json: string (optional, TipTap JSON)
 * - content_html: string (optional, sanitized HTML)
 * - cover_image: string (optional)
 * - featured_image_id: int (optional)
 * - instagram_image: string (optional)
 * - twitter_image: string (optional)
 * - facebook_image: string (optional)
 * - tags: array (optional)
 * - categories: array (optional)
 * - universe_tag: string (optional)
 * - status: string (draft|published|scheduled)
 * - scheduled_at: datetime (required if status is scheduled)
 * - og_title: string (optional)
 * - og_description: string (optional)
 * - meta_description: string (optional)
 * - primary_keywords: string (optional)
 * - longtail_keywords: string (optional)
 * - change_summary: string (optional, description of changes)
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');

// Only allow POST requests
require_method(['POST']);

// Require authentication
requireAuth();

// Rate limit: 10 requests/minute (authenticated users)
requireRateLimit('blog:update', 10, 60, $_SESSION['user_id'], true);

try {
    $pdo = db();
    $input = body_json();
    
    // Validate post ID
    if (empty($input['id'])) {
        json_error('Post ID is required', 400);
    }
    
    $postId = intval($input['id']);
    
    // Fetch existing post
    $existingStmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $existingStmt->execute([$postId]);
    $existing = $existingStmt->fetch();
    
    if (!$existing) {
        json_error('Post not found', 404);
    }
    
    // Check if content is being updated (for revision tracking)
    $contentChanged = false;
    $contentJson = $existing['content_json'];
    $contentHtml = $existing['content_html'];
    
    if (isset($input['content_json'])) {
        $decoded = json_decode($input['content_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            json_error('Invalid content JSON format', 400);
        }
        if (!isset($decoded['type']) || $decoded['type'] !== 'doc') {
            json_error('Invalid TipTap JSON structure', 400);
        }
        $contentJson = $input['content_json'];
        $contentChanged = $contentJson !== $existing['content_json'];
    }
    
    if (isset($input['content_html'])) {
        $contentHtml = sanitizeHtmlContent($input['content_html']);
        $contentChanged = $contentChanged || $contentHtml !== $existing['content_html'];
    }
    
    // Handle slug updates
    $slug = $existing['slug'];
    if (isset($input['slug']) && $input['slug'] !== $existing['slug']) {
        $newSlug = sanitizeSlug($input['slug']);
        $slug = generateUniqueSlug($pdo, $newSlug, $postId);
    }
    
    // Calculate reading time if content changed
    $readingTime = $existing['reading_time'];
    if ($contentChanged) {
        $wordCount = str_word_count(strip_tags($contentHtml));
        $readingTime = max(1, ceil($wordCount / 200));
    }
    
    // Handle status changes
    $status = isset($input['status']) ? $input['status'] : $existing['status'];
    $validStatuses = ['draft', 'published', 'scheduled'];
    if (!in_array($status, $validStatuses)) {
        json_error('Invalid status', 400);
    }
    
    // Handle published_at and first_published_at (for social media spam prevention)
    $publishedAt = $existing['published_at'];
    $firstPublishedAt = $existing['first_published_at'] ?? null;
    $isFirstPublish = false;
    
    if ($status === 'published' && $existing['status'] !== 'published') {
        $publishedAt = date('Y-m-d H:i:s');
        
        // Check if this is the FIRST time being published
        if (empty($firstPublishedAt)) {
            $firstPublishedAt = $publishedAt;
            $isFirstPublish = true; // Allow crossposting only on first publish
        }
    }
    
    // Handle scheduled_at
    $scheduledAt = $existing['scheduled_at'];
    if ($status === 'scheduled') {
        if (isset($input['scheduled_at'])) {
            $scheduledAt = date('Y-m-d H:i:s', strtotime($input['scheduled_at']));
            if (strtotime($scheduledAt) <= time()) {
                json_error('Scheduled date must be in the future', 400);
            }
        } elseif (!$scheduledAt) {
            json_error('Scheduled date is required for scheduled posts', 400);
        }
    }
    
    // Process tags and categories
    $tags = $existing['tags'];
    if (isset($input['tags'])) {
        $tags = is_array($input['tags']) 
            ? json_encode(array_values(array_filter($input['tags'])))
            : null;
    }
    
    $categories = $existing['categories'];
    if (isset($input['categories'])) {
        $categories = is_array($input['categories'])
            ? json_encode(array_values(array_filter($input['categories'])))
            : null;
    }
    
    // Build update query dynamically
    $title = $input['title'] ?? $existing['title'];
    $excerpt = array_key_exists('excerpt', $input) ? $input['excerpt'] : $existing['excerpt'];
    
    $stmt = $pdo->prepare("
        UPDATE blog_posts SET
            slug = ?,
            title = ?,
            excerpt = ?,
            content_json = ?,
            content_html = ?,
            cover_image = ?,
            featured_image_id = ?,
            instagram_image = ?,
            instagram_image_id = ?,
            twitter_image = ?,
            twitter_image_id = ?,
            facebook_image = ?,
            facebook_image_id = ?,
            og_title = ?,
            og_description = ?,
            meta_description = ?,
            primary_keywords = ?,
            longtail_keywords = ?,
            tags = ?,
            categories = ?,
            universe_tag = ?,
            status = ?,
            published_at = ?,
            first_published_at = COALESCE(first_published_at, ?),
            scheduled_at = ?,
            reading_time = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([
        $slug,
        $title,
        $excerpt,
        $contentJson,
        $contentHtml,
        array_key_exists('cover_image', $input) ? $input['cover_image'] : $existing['cover_image'],
        array_key_exists('featured_image_id', $input) ? $input['featured_image_id'] : $existing['featured_image_id'],
        array_key_exists('instagram_image', $input) ? $input['instagram_image'] : $existing['instagram_image'],
        array_key_exists('instagram_image_id', $input) ? $input['instagram_image_id'] : $existing['instagram_image_id'],
        array_key_exists('twitter_image', $input) ? $input['twitter_image'] : $existing['twitter_image'],
        array_key_exists('twitter_image_id', $input) ? $input['twitter_image_id'] : $existing['twitter_image_id'],
        array_key_exists('facebook_image', $input) ? $input['facebook_image'] : $existing['facebook_image'],
        array_key_exists('facebook_image_id', $input) ? $input['facebook_image_id'] : $existing['facebook_image_id'],
        array_key_exists('og_title', $input) ? $input['og_title'] : $existing['og_title'],
        array_key_exists('og_description', $input) ? $input['og_description'] : $existing['og_description'],
        array_key_exists('meta_description', $input) ? $input['meta_description'] : $existing['meta_description'],
        array_key_exists('primary_keywords', $input) ? $input['primary_keywords'] : $existing['primary_keywords'],
        array_key_exists('longtail_keywords', $input) ? $input['longtail_keywords'] : $existing['longtail_keywords'],
        $tags,
        $categories,
        array_key_exists('universe_tag', $input) ? $input['universe_tag'] : $existing['universe_tag'],
        $status,
        $publishedAt,
        $firstPublishedAt, // Only set if not already set (COALESCE in SQL)
        $scheduledAt,
        $readingTime,
        $postId
    ]);
    
    // Create revision if content changed
    if ($contentChanged || $title !== $existing['title'] || $excerpt !== $existing['excerpt']) {
        $changeSummary = $input['change_summary'] ?? 'Content updated';
        
        $revisionStmt = $pdo->prepare("
            INSERT INTO blog_revisions (
                post_id, content_json, content_html, title, excerpt,
                edited_by, change_summary, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $revisionStmt->execute([
            $postId,
            $contentJson,
            $contentHtml,
            $title,
            $excerpt,
            $_SESSION['user_id'],
            $changeSummary
        ]);
    }
    
    // Fetch updated post
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
        // Flag for frontend: only allow crossposting on FIRST publish
        // Prevents social media spam when updating published posts
        'is_first_publish' => $isFirstPublish,
        'allow_crosspost' => $isFirstPublish && $status === 'published'
    ]);

} catch (Exception $e) {
    error_log("Blog update error: " . $e->getMessage());
    json_error('Failed to update blog post', 500);
}

/**
 * Sanitize slug to URL-friendly format
 */
function sanitizeSlug($title) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    
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
    $allowedTags = '<p><br><strong><b><em><i><u><s><strike><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code><img><figure><figcaption><hr><table><thead><tbody><tr><th><td><span><div>';
    $html = strip_tags($html, $allowedTags);
    $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    $html = preg_replace('/javascript:/i', '', $html);
    return $html;
}
