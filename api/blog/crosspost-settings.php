<?php
/**
 * Blog Crosspost Settings API
 *
 * Manages per-blog-post social media crosspost settings
 *
 * GET /api/blog/crosspost-settings.php?blog_post_id=123
 * - Returns crosspost settings and posting history for a blog post
 *
 * POST /api/blog/crosspost-settings.php
 * - Saves crosspost settings for a blog post
 * - Request Body: { blog_post_id, settings: [{ platform, enabled, custom_message }] }
 *
 * @package AuthorCMS
 * @since Phase 4 - Social Media Integration
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');

// Require authentication
requireAuth();

// GET: Fetch crosspost settings for a blog post
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $blogPostId = $_GET['blog_post_id'] ?? null;

    if (!$blogPostId) {
        json_error('blog_post_id is required', 400);
    }

    try {
        $pdo = db();

        // Verify post exists
        $postStmt = $pdo->prepare("SELECT id FROM blog_posts WHERE id = ?");
        $postStmt->execute([$blogPostId]);
        if (!$postStmt->fetch()) {
            json_error('Blog post not found', 404);
        }

        // Get crosspost settings
        $settingsStmt = $pdo->prepare("
            SELECT platform, enabled, custom_message
            FROM blog_crosspost_settings
            WHERE blog_post_id = ?
        ");
        $settingsStmt->execute([$blogPostId]);
        $settings = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get social post history
        $historyStmt = $pdo->prepare("
            SELECT platform, status, post_url, platform_post_id, posted_at, error_message, retry_count
            FROM blog_social_posts
            WHERE blog_post_id = ?
            ORDER BY posted_at DESC
        ");
        $historyStmt->execute([$blogPostId]);
        $socialPosts = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        json_response([
            'success' => true,
            'settings' => $settings,
            'social_posts' => $socialPosts
        ]);

    } catch (Exception $e) {
        error_log("Crosspost settings fetch error: " . $e->getMessage());
        json_error('Failed to fetch crosspost settings', 500);
    }
}

// POST: Save crosspost settings
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRateLimit('blog:crosspost-settings', 20, 60, $_SESSION['user_id'], true);

    try {
        $pdo = db();
        $input = body_json();

        $blogPostId = $input['blog_post_id'] ?? null;
        $settings = $input['settings'] ?? null;

        if (!$blogPostId) {
            json_error('blog_post_id is required', 400);
        }

        if (!is_array($settings)) {
            json_error('settings must be an array', 400);
        }

        // Verify post exists
        $postStmt = $pdo->prepare("SELECT id FROM blog_posts WHERE id = ?");
        $postStmt->execute([$blogPostId]);
        if (!$postStmt->fetch()) {
            json_error('Blog post not found', 404);
        }

        // Valid platforms
        $validPlatforms = ['instagram', 'twitter', 'facebook', 'discord', 'threads', 'bluesky', 'youtube'];

        // Begin transaction
        $pdo->beginTransaction();

        try {
            // Clear existing settings for this post
            $deleteStmt = $pdo->prepare("DELETE FROM blog_crosspost_settings WHERE blog_post_id = ?");
            $deleteStmt->execute([$blogPostId]);

            // Insert new settings
            $insertStmt = $pdo->prepare("
                INSERT INTO blog_crosspost_settings (blog_post_id, platform, enabled, custom_message, updated_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            foreach ($settings as $setting) {
                $platform = $setting['platform'] ?? null;
                $enabled = $setting['enabled'] ?? false;
                $customMessage = $setting['custom_message'] ?? null;

                if (!$platform || !in_array($platform, $validPlatforms)) {
                    continue; // Skip invalid platforms
                }

                $insertStmt->execute([
                    $blogPostId,
                    $platform,
                    $enabled ? 1 : 0,
                    $customMessage
                ]);
            }

            $pdo->commit();

            json_response([
                'success' => true,
                'message' => 'Crosspost settings saved successfully'
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        error_log("Crosspost settings save error: " . $e->getMessage());
        json_error('Failed to save crosspost settings', 500);
    }
}

else {
    require_method(['GET', 'POST']);
}
