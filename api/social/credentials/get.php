<?php
/**
 * Get Social Media Credentials Status
 * 
 * Returns the status of all configured social media platform credentials.
 * Does NOT return actual tokens - only connection status info.
 *
 * @endpoint GET /api/social/credentials/get.php
 * @auth Required (Admin)
 * @package AuthorCMS
 * @since Phase 4 - Social Media Integration
 */

require_once __DIR__ . '/../../bootstrap.php';

// Require authentication
require_method(['GET']);
require_auth();

try {
    // Define all supported platforms
    $supportedPlatforms = ['instagram', 'twitter', 'facebook', 'discord', 'threads', 'bluesky', 'youtube'];
    
    // Fetch credentials for all platforms
    $stmt = db()->prepare("
        SELECT 
            platform,
            is_active,
            last_used_at,
            token_expires_at,
            config,
            created_at,
            updated_at,
            CASE WHEN access_token IS NOT NULL AND access_token != '' THEN 1 ELSE 0 END as has_token
        FROM social_api_credentials
        ORDER BY platform
    ");
    $stmt->execute();
    $credentials = $stmt->fetchAll();
    
    // Index by platform
    $credentialsByPlatform = [];
    foreach ($credentials as $cred) {
        $platform = $cred['platform'];
        $config = $cred['config'] ? json_decode($cred['config'], true) : [];
        
        // Determine if credentials exist (platform-specific)
        // Discord uses webhook_url instead of access_token
        $hasCredentials = false;
        if ($platform === 'discord') {
            $hasCredentials = isset($config['webhook_url']) && !empty($config['webhook_url']);
        } else {
            $hasCredentials = (bool)$cred['has_token'];
        }
        
        // Determine connection status
        $isConnected = $hasCredentials && $cred['is_active'];
        $isExpired = $cred['token_expires_at'] && strtotime($cred['token_expires_at']) < time();
        $expiresIn = null;
        $expiresInDays = null;
        
        if ($cred['token_expires_at']) {
            $expiresAt = strtotime($cred['token_expires_at']);
            $expiresIn = $expiresAt - time();
            $expiresInDays = ceil($expiresIn / 86400);
        }
        
        // Calculate status
        $status = 'not_connected';
        if ($isConnected) {
            if ($isExpired) {
                $status = 'expired';
            } elseif ($expiresInDays !== null && $expiresInDays <= 7) {
                $status = 'expiring_soon';
            } else {
                $status = 'connected';
            }
        }
        
        $credentialsByPlatform[$platform] = [
            'platform' => $platform,
            'status' => $status,
            'is_active' => (bool)$cred['is_active'],
            'has_credentials' => $hasCredentials,
            'expires_at' => $cred['token_expires_at'],
            'expires_in_days' => $expiresInDays,
            'last_used_at' => $cred['last_used_at'],
            'created_at' => $cred['created_at'],
            'updated_at' => $cred['updated_at'],
            // Platform-specific config (non-sensitive fields only)
            'config' => [
                'has_page_id' => isset($config['page_id']) || isset($config['facebook_page_id']),
                'has_webhook_url' => isset($config['webhook_url']),
                'has_instagram_user_id' => isset($config['instagram_user_id']),
            ]
        ];
    }
    
    // Build response with all platforms (including unconfigured)
    $platforms = [];
    foreach ($supportedPlatforms as $platform) {
        if (isset($credentialsByPlatform[$platform])) {
            $platforms[$platform] = $credentialsByPlatform[$platform];
        } else {
            // Platform not configured
            $platforms[$platform] = [
                'platform' => $platform,
                'status' => 'not_connected',
                'is_active' => false,
                'has_credentials' => false,
                'expires_at' => null,
                'expires_in_days' => null,
                'last_used_at' => null,
                'created_at' => null,
                'updated_at' => null,
                'config' => [
                    'has_page_id' => false,
                    'has_webhook_url' => false,
                    'has_instagram_user_id' => false,
                ]
            ];
        }
    }
    
    // Get additional stats
    $connectedCount = 0;
    $expiringCount = 0;
    foreach ($platforms as $p) {
        if ($p['status'] === 'connected') $connectedCount++;
        if ($p['status'] === 'expiring_soon') $expiringCount++;
    }
    
    json_response([
        'success' => true,
        'platforms' => $platforms,
        'summary' => [
            'total_platforms' => count($supportedPlatforms),
            'connected' => $connectedCount,
            'expiring_soon' => $expiringCount,
        ]
    ]);

} catch (Exception $e) {
    error_log("Get social credentials error: " . $e->getMessage());
    json_error('Failed to fetch credentials: ' . $e->getMessage(), 500);
}
