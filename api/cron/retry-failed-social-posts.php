<?php
/**
 * Retry Failed Social Posts Cron Job
 * 
 * Runs hourly to retry failed social media crosspost attempts.
 * Implements exponential backoff (1h → 4h → 12h → 24h → give up).
 *
 * Cron schedule: 0 * * * * (every hour)
 * Command: php /path/to/api/cron/retry-failed-social-posts.php
 *
 * @package AuthorCMS
 * @since Phase 4 - Social Media Integration
 */

// Only allow CLI execution (not web requests)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from CLI');
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../social/helpers/encryption.php';
require_once __DIR__ . '/../social/platforms/instagram.php';
require_once __DIR__ . '/../social/platforms/twitter.php';
require_once __DIR__ . '/../social/platforms/facebook.php';
require_once __DIR__ . '/../social/platforms/discord.php';

// Configuration
$maxRetries = 5; // Give up after 5 attempts
$retryBackoffHours = [1, 4, 12, 24, 48]; // Hours to wait between retries
$batchSize = 10; // Process max 10 failed posts per run

echo "[" . date('Y-m-d H:i:s') . "] Starting failed social posts retry job\n";

try {
    // Find failed posts eligible for retry
    // Includes exponential backoff check
    $stmt = db()->prepare("
        SELECT 
            bsp.*,
            bp.title as post_title,
            bp.slug as post_slug,
            bp.excerpt,
            bp.tags,
            bp.cover_image,
            bp.instagram_image,
            bp.twitter_image,
            bp.facebook_image,
            u.username as author_name
        FROM blog_social_posts bsp
        JOIN blog_posts bp ON bsp.blog_post_id = bp.id
        LEFT JOIN users u ON bp.author_id = u.id
        WHERE bsp.status = 'failed'
          AND bsp.retry_count < ?
          AND bsp.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY bsp.retry_count ASC, bsp.created_at ASC
        LIMIT ?
    ");
    $stmt->execute([$maxRetries, $batchSize]);
    $failedPosts = $stmt->fetchAll();
    
    echo "Found " . count($failedPosts) . " failed posts to retry\n";
    
    if (empty($failedPosts)) {
        echo "No failed posts to retry. Exiting.\n";
        exit(0);
    }
    
    $successCount = 0;
    $failCount = 0;
    $skippedCount = 0;
    
    foreach ($failedPosts as $failed) {
        $platform = $failed['platform'];
        $blogPostId = $failed['blog_post_id'];
        $retryCount = $failed['retry_count'];
        
        // Calculate backoff - check if enough time has passed since last attempt
        $backoffHours = $retryBackoffHours[min($retryCount, count($retryBackoffHours) - 1)];
        $lastAttempt = strtotime($failed['created_at']);
        $nextRetryTime = $lastAttempt + ($backoffHours * 3600);
        
        if (time() < $nextRetryTime) {
            $waitMinutes = ceil(($nextRetryTime - time()) / 60);
            echo "  [{$platform}] Post #{$blogPostId}: Waiting {$waitMinutes} more minutes (backoff)\n";
            $skippedCount++;
            continue;
        }
        
        echo "  [{$platform}] Post #{$blogPostId} ('{$failed['post_title']}'): Attempt #" . ($retryCount + 1) . "...\n";
        
        // Get credentials
        $credentials = getDecryptedCredentials($platform);
        
        if (!$credentials || !$credentials['is_active']) {
            echo "    ✗ Platform {$platform} not configured or disabled\n";
            $failCount++;
            continue;
        }
        
        // Build base URL
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        
        // Prepare post data
        $postData = [
            'title' => $failed['post_title'],
            'excerpt' => $failed['excerpt'],
            'url' => $baseUrl . '/blog/' . $failed['post_slug'],
            'tags' => json_decode($failed['tags'] ?? '[]', true) ?: [],
            'cover_image' => $failed['cover_image'] ? ($baseUrl . $failed['cover_image']) : null,
            'instagram_image' => $failed['instagram_image'] ? ($baseUrl . $failed['instagram_image']) : null,
            'twitter_image' => $failed['twitter_image'] ? ($baseUrl . $failed['twitter_image']) : null,
            'facebook_image' => $failed['facebook_image'] ? ($baseUrl . $failed['facebook_image']) : null,
            'author_name' => $failed['author_name'] ?? 'Author',
        ];
        
        // Check for custom message in crosspost settings
        $settingsStmt = db()->prepare("
            SELECT custom_message FROM blog_crosspost_settings 
            WHERE blog_post_id = ? AND platform = ?
        ");
        $settingsStmt->execute([$blogPostId, $platform]);
        $settings = $settingsStmt->fetch();
        if ($settings && $settings['custom_message']) {
            $postData['custom_message'] = $settings['custom_message'];
        }
        
        // Attempt retry
        try {
            $result = postToPlatform($platform, $postData, $credentials);
            
            if ($result['success']) {
                // Update to success
                $updateStmt = db()->prepare("
                    UPDATE blog_social_posts 
                    SET status = 'success', 
                        error_message = NULL,
                        platform_post_id = ?,
                        post_url = ?,
                        posted_at = NOW(),
                        retry_count = retry_count + 1
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $result['platform_post_id'],
                    $result['post_url'],
                    $failed['id']
                ]);
                
                echo "    ✓ Success! Post URL: {$result['post_url']}\n";
                $successCount++;
            } else {
                // Update retry count and error
                $updateStmt = db()->prepare("
                    UPDATE blog_social_posts 
                    SET retry_count = retry_count + 1,
                        error_message = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$result['error'], $failed['id']]);
                
                echo "    ✗ Failed: {$result['error']}\n";
                $failCount++;
                
                // Check if max retries reached
                if ($retryCount + 1 >= $maxRetries) {
                    echo "    ⚠ Max retries reached. Marking as permanently failed.\n";
                    sendFailureNotification($failed, $result['error']);
                }
            }
            
        } catch (Exception $e) {
            // Update with exception error
            $updateStmt = db()->prepare("
                UPDATE blog_social_posts 
                SET retry_count = retry_count + 1,
                    error_message = ?
                WHERE id = ?
            ");
            $updateStmt->execute(['Exception: ' . $e->getMessage(), $failed['id']]);
            
            echo "    ✗ Exception: {$e->getMessage()}\n";
            $failCount++;
        }
        
        // Brief delay between retries to avoid rate limits
        sleep(1);
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Retry job complete\n";
    echo "  Success: {$successCount}\n";
    echo "  Failed: {$failCount}\n";
    echo "  Skipped (backoff): {$skippedCount}\n";
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    error_log("Social retry cron error: " . $e->getMessage());
    exit(1);
}

/**
 * Get decrypted credentials for a platform
 */
function getDecryptedCredentials(string $platform): ?array {
    $stmt = db()->prepare("SELECT * FROM social_api_credentials WHERE platform = ?");
    $stmt->execute([$platform]);
    $creds = $stmt->fetch();
    
    if (!$creds) return null;
    
    $decrypted = [
        'platform' => $creds['platform'],
        'is_active' => (bool)$creds['is_active'],
        'access_token' => null,
        'config' => $creds['config'] ? json_decode($creds['config'], true) : [],
    ];
    
    if ($creds['access_token']) {
        try {
            $decrypted['access_token'] = decryptToken($creds['access_token']);
        } catch (Exception $e) {
            return null;
        }
    }
    
    return $decrypted;
}

/**
 * Post to a specific platform
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
            return ['success' => false, 'error' => 'Unsupported platform'];
    }
}

/**
 * Send notification when max retries reached (optional)
 */
function sendFailureNotification(array $failed, string $error): void {
    // Check if admin email is configured
    if (!defined('ADMIN_EMAIL') || !ADMIN_EMAIL) {
        return;
    }
    
    $subject = "[Author CMS] Social post permanently failed: {$failed['platform']}";
    $message = "A social media crosspost has failed after multiple retries.\n\n";
    $message .= "Blog Post: {$failed['post_title']}\n";
    $message .= "Platform: {$failed['platform']}\n";
    $message .= "Retry Count: {$failed['retry_count']}\n";
    $message .= "Last Error: {$error}\n\n";
    $message .= "You may need to manually post or investigate the issue.";
    
    @mail(ADMIN_EMAIL, $subject, $message);
}
