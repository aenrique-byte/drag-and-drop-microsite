<?php
/**
 * Social Media Crosspost Orchestrator
 * 
 * Main endpoint for crossposting blog posts to social media platforms.
 * Coordinates posting to all enabled platforms and tracks results.
 *
 * @endpoint POST /api/social/post.php
 * @auth Required (Admin)
 * @package AuthorCMS
 * @since Phase 4 - Social Media Integration
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/helpers/encryption.php';
require_once __DIR__ . '/platforms/instagram.php';
require_once __DIR__ . '/platforms/twitter.php';
require_once __DIR__ . '/platforms/facebook.php';
require_once __DIR__ . '/platforms/discord.php';

// Require authentication
require_method(['POST']);
require_auth();

// Rate limit: 10 crosspost operations per minute
requireRateLimit('social_crosspost', 10, 60);

try {
    $input = body_json();
    
    // Required: blog_post_id
    $blogPostId = $input['blog_post_id'] ?? null;
    
    if (!$blogPostId) {
        json_error('blog_post_id is required', 400);
    }
    
    // Optional: specific platforms (default: all enabled for this post)
    $platformsToPost = $input['platforms'] ?? null;
    
    // Optional: custom messages override
    $customMessages = $input['custom_messages'] ?? [];
    
    // Fetch blog post
    $stmt = db()->prepare("
        SELECT 
            bp.*,
            u.username as author_name,
            img.original_path as featured_image_path
        FROM blog_posts bp
        LEFT JOIN users u ON bp.author_id = u.id
        LEFT JOIN images img ON bp.featured_image_id = img.id
        WHERE bp.id = ?
    ");
    $stmt->execute([$blogPostId]);
    $post = $stmt->fetch();
    
    if (!$post) {
        json_error('Blog post not found', 404);
    }
    
    // Build base URL for the post
    // Try BASE_URL constant first, fall back to building from server variables
    if (defined('BASE_URL') && !empty(BASE_URL)) {
        $baseUrl = rtrim(BASE_URL, '/');
    } else {
        // Dynamically build base URL from server variables
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
    }
    $postUrl = $baseUrl . '/blog/' . $post['slug'];
    
    // Prepare post data for formatters
    $postData = [
        'title' => $post['title'],
        'excerpt' => $post['excerpt'],
        'url' => $postUrl,
        'tags' => json_decode($post['tags'] ?? '[]', true) ?: [],
        'cover_image' => $post['cover_image'] ? ($baseUrl . $post['cover_image']) : null,
        'featured_image' => $post['featured_image_path'] ? ($baseUrl . $post['featured_image_path']) : null,
        'instagram_image' => $post['instagram_image'] ? ($baseUrl . $post['instagram_image']) : null,
        'twitter_image' => $post['twitter_image'] ? ($baseUrl . $post['twitter_image']) : null,
        'facebook_image' => $post['facebook_image'] ? ($baseUrl . $post['facebook_image']) : null,
        'author_name' => $post['author_name'] ?? 'Author',
    ];
    
    // Get enabled platforms for this post
    $enabledPlatforms = [];
    
    if ($platformsToPost) {
        // Use specified platforms
        $enabledPlatforms = $platformsToPost;
    } else {
        // Get from crosspost settings
        $stmt = db()->prepare("
            SELECT platform FROM blog_crosspost_settings 
            WHERE blog_post_id = ? AND enabled = 1
        ");
        $stmt->execute([$blogPostId]);
        $enabledPlatforms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    if (empty($enabledPlatforms)) {
        json_response([
            'success' => true,
            'message' => 'No platforms enabled for crossposting',
            'results' => []
        ]);
    }
    
    // Results storage
    $results = [];
    $successCount = 0;
    $failCount = 0;
    
    // Process each platform
    foreach ($enabledPlatforms as $platform) {
        $platform = strtolower($platform);
        
        // Check if already posted
        $stmt = db()->prepare("
            SELECT id, status, platform_post_id FROM blog_social_posts 
            WHERE blog_post_id = ? AND platform = ?
        ");
        $stmt->execute([$blogPostId, $platform]);
        $existingPost = $stmt->fetch();
        
        if ($existingPost && $existingPost['status'] === 'success') {
            $results[$platform] = [
                'success' => true,
                'skipped' => true,
                'message' => 'Already posted',
                'platform_post_id' => $existingPost['platform_post_id']
            ];
            continue;
        }
        
        // Get credentials for platform
        $credentials = getDecryptedCredentials($platform);
        
        if (!$credentials || !$credentials['is_active']) {
            $results[$platform] = [
                'success' => false,
                'error' => ucfirst($platform) . ' not configured or disabled'
            ];
            $failCount++;
            logSocialPostAttempt($blogPostId, $platform, 'failed', ucfirst($platform) . ' not configured');
            continue;
        }
        
        // Add custom message if provided
        if (isset($customMessages[$platform])) {
            $postData['custom_message'] = $customMessages[$platform];
        } else {
            // Check for custom message in crosspost settings
            $stmt = db()->prepare("
                SELECT custom_message FROM blog_crosspost_settings 
                WHERE blog_post_id = ? AND platform = ?
            ");
            $stmt->execute([$blogPostId, $platform]);
            $settings = $stmt->fetch();
            if ($settings && $settings['custom_message']) {
                $postData['custom_message'] = $settings['custom_message'];
            } else {
                unset($postData['custom_message']);
            }
        }
        
        // Post to platform
        try {
            $result = postToPlatform($platform, $postData, $credentials);
            
            if ($result['success']) {
                $successCount++;
                logSocialPostAttempt(
                    $blogPostId, 
                    $platform, 
                    'success', 
                    null, 
                    $result['platform_post_id'], 
                    $result['post_url']
                );
            } else {
                $failCount++;
                logSocialPostAttempt($blogPostId, $platform, 'failed', $result['error']);
            }
            
            $results[$platform] = $result;
            
        } catch (Exception $e) {
            $failCount++;
            $error = 'Exception: ' . $e->getMessage();
            error_log("Social posting error ({$platform}): " . $e->getMessage());
            logSocialPostAttempt($blogPostId, $platform, 'failed', $error);
            
            $results[$platform] = [
                'success' => false,
                'error' => $error
            ];
        }
        
        // Update last_used_at for credentials
        db()->prepare("
            UPDATE social_api_credentials SET last_used_at = NOW() WHERE platform = ?
        ")->execute([$platform]);
    }
    
    json_response([
        'success' => $failCount === 0,
        'message' => sprintf(
            'Posted to %d platform(s), %d failed',
            $successCount,
            $failCount
        ),
        'results' => $results,
        'summary' => [
            'total' => count($enabledPlatforms),
            'success' => $successCount,
            'failed' => $failCount
        ]
    ]);

} catch (Exception $e) {
    error_log("Social crosspost orchestrator error: " . $e->getMessage());
    json_error('Crosspost failed: ' . $e->getMessage(), 500);
}

/**
 * Get decrypted credentials for a platform
 *
 * @param string $platform Platform name
 * @return array|null Credentials with decrypted tokens
 */
function getDecryptedCredentials(string $platform): ?array {
    $stmt = db()->prepare("
        SELECT * FROM social_api_credentials 
        WHERE platform = ?
    ");
    $stmt->execute([$platform]);
    $creds = $stmt->fetch();
    
    if (!$creds) {
        return null;
    }
    
    // Decrypt tokens
    $decrypted = [
        'platform' => $creds['platform'],
        'is_active' => (bool)$creds['is_active'],
        'access_token' => null,
        'refresh_token' => null,
        'config' => $creds['config'] ? json_decode($creds['config'], true) : [],
        'token_expires_at' => $creds['token_expires_at']
    ];
    
    if ($creds['access_token']) {
        try {
            $decrypted['access_token'] = decryptToken($creds['access_token']);
        } catch (Exception $e) {
            error_log("Failed to decrypt {$platform} access token: " . $e->getMessage());
            return null;
        }
    }
    
    if ($creds['refresh_token']) {
        try {
            $decrypted['refresh_token'] = decryptToken($creds['refresh_token']);
        } catch (Exception $e) {
            // Non-fatal - refresh token not always required
        }
    }
    
    return $decrypted;
}

/**
 * Post to a specific platform
 *
 * @param string $platform Platform name
 * @param array $postData Post data
 * @param array $credentials Decrypted credentials
 * @return array Result
 */
function postToPlatform(string $platform, array $postData, array $credentials): array {
    switch ($platform) {
        case 'instagram':
            return postToInstagram($postData, $credentials);
            
        case 'twitter':
            return postToTwitter($postData, $credentials);
            
        case 'facebook':
            return postToFacebook($postData, $credentials);
            
        case 'discord':
            return postToDiscord($postData, $credentials);
            
        default:
            return [
                'success' => false,
                'platform_post_id' => null,
                'post_url' => null,
                'error' => 'Unsupported platform: ' . $platform
            ];
    }
}

/**
 * Log social post attempt to database
 *
 * @param int $blogPostId Blog post ID
 * @param string $platform Platform name
 * @param string $status 'pending', 'success', or 'failed'
 * @param string|null $error Error message if failed
 * @param string|null $platformPostId Platform's post ID if successful
 * @param string|null $postUrl URL to the post if successful
 */
function logSocialPostAttempt(
    int $blogPostId,
    string $platform,
    string $status,
    ?string $error = null,
    ?string $platformPostId = null,
    ?string $postUrl = null
): void {
    $stmt = db()->prepare("
        INSERT INTO blog_social_posts 
        (blog_post_id, platform, status, error_message, platform_post_id, post_url, posted_at, retry_count)
        VALUES (?, ?, ?, ?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE 
            status = VALUES(status),
            error_message = VALUES(error_message),
            platform_post_id = COALESCE(VALUES(platform_post_id), platform_post_id),
            post_url = COALESCE(VALUES(post_url), post_url),
            posted_at = CASE WHEN VALUES(status) = 'success' THEN NOW() ELSE posted_at END,
            retry_count = retry_count + 1
    ");
    
    $postedAt = $status === 'success' ? date('Y-m-d H:i:s') : null;
    
    $stmt->execute([
        $blogPostId,
        $platform,
        $status,
        $error,
        $platformPostId,
        $postUrl,
        $postedAt
    ]);
}
