<?php
/**
 * Test Crosspost Debug Endpoint
 *
 * Debug endpoint to test crossposting without triggering actual posts
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');

// Require authentication
require_method(['POST']);
requireAuth();

try {
    $input = body_json();
    $blogPostId = $input['blog_post_id'] ?? null;

    if (!$blogPostId) {
        json_error('blog_post_id is required', 400);
    }

    $pdo = db();

    // Get blog post
    $stmt = $pdo->prepare("
        SELECT bp.*, u.username as author_name
        FROM blog_posts bp
        LEFT JOIN users u ON bp.author_id = u.id
        WHERE bp.id = ?
    ");
    $stmt->execute([$blogPostId]);
    $post = $stmt->fetch();

    if (!$post) {
        json_error('Blog post not found', 404);
    }

    // Get enabled platforms
    $stmt = $pdo->prepare("
        SELECT platform, enabled, custom_message
        FROM blog_crosspost_settings
        WHERE blog_post_id = ? AND enabled = 1
    ");
    $stmt->execute([$blogPostId]);
    $enabledPlatforms = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get Discord credentials
    $stmt = $pdo->prepare("
        SELECT platform, is_active, config
        FROM social_api_credentials
        WHERE platform = 'discord'
    ");
    $stmt->execute();
    $discordCreds = $stmt->fetch();

    json_response([
        'success' => true,
        'debug' => [
            'post_id' => $blogPostId,
            'post_title' => $post['title'],
            'post_status' => $post['status'],
            'enabled_platforms' => $enabledPlatforms,
            'discord_configured' => $discordCreds ? true : false,
            'discord_active' => $discordCreds ? (bool)$discordCreds['is_active'] : false,
            'discord_has_webhook' => $discordCreds ? !empty(json_decode($discordCreds['config'], true)['webhook_url'] ?? null) : false
        ]
    ]);

} catch (Exception $e) {
    error_log("Test crosspost error: " . $e->getMessage());
    json_error('Test failed: ' . $e->getMessage(), 500);
}
