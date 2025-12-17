<?php
/**
 * Debug Crosspost Settings
 * Shows what's actually in the database and URL generation
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');

requireAuth();

try {
    $blogPostId = $_GET['blog_post_id'] ?? 2; // Default to post ID 2

    $pdo = db();

    // Test URL generation (same logic as post.php)
    if (defined('BASE_URL') && !empty(BASE_URL)) {
        $baseUrl = rtrim(BASE_URL, '/');
        $urlSource = 'BASE_URL constant';
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        $urlSource = 'auto-detected from $_SERVER';
    }

    // Get crosspost settings
    $stmt = $pdo->prepare("
        SELECT * FROM blog_crosspost_settings
        WHERE blog_post_id = ?
    ");
    $stmt->execute([$blogPostId]);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get Discord credentials
    $stmt = $pdo->prepare("
        SELECT platform, is_active, access_token, config
        FROM social_api_credentials
        WHERE platform = 'discord'
    ");
    $stmt->execute();
    $discordCreds = $stmt->fetch();

    // Decode config
    $config = null;
    if ($discordCreds && $discordCreds['config']) {
        $config = json_decode($discordCreds['config'], true);
    }

    // Get blog post to show example URL
    $stmt = $pdo->prepare("SELECT slug, cover_image FROM blog_posts WHERE id = ?");
    $stmt->execute([$blogPostId]);
    $blogPost = $stmt->fetch();

    $examplePostUrl = $blogPost ? $baseUrl . '/blog/' . $blogPost['slug'] : null;
    $exampleCoverUrl = $blogPost && $blogPost['cover_image'] ? $baseUrl . $blogPost['cover_image'] : null;

    json_response([
        'success' => true,
        'blog_post_id' => $blogPostId,
        'url_generation' => [
            'base_url' => $baseUrl,
            'url_source' => $urlSource,
            'example_post_url' => $examplePostUrl,
            'example_cover_url' => $exampleCoverUrl,
            'is_absolute_url' => str_starts_with($baseUrl, 'http'),
            'note' => 'Discord requires absolute URLs (starting with http/https). If is_absolute_url is false, crossposting will fail.'
        ],
        'crosspost_settings' => $settings,
        'discord_credentials' => [
            'exists' => $discordCreds ? true : false,
            'is_active' => $discordCreds ? (int)$discordCreds['is_active'] : null,
            'has_access_token' => $discordCreds && $discordCreds['access_token'] ? true : false,
            'config' => $config,
            'has_webhook_url' => $config && !empty($config['webhook_url']) ? true : false
        ]
    ]);

} catch (Exception $e) {
    json_error('Debug failed: ' . $e->getMessage(), 500);
}
