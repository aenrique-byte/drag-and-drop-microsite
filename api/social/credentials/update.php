<?php
/**
 * Update Social Media Credentials
 * 
 * Saves or updates social media platform credentials with encryption.
 * Supports manual token entry (Phase 4 approach).
 *
 * @endpoint POST /api/social/credentials/update.php
 * @auth Required (Admin)
 * @package AuthorCMS
 * @since Phase 4 - Social Media Integration
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../helpers/encryption.php';

// Require authentication
require_method(['POST']);
require_auth();

// Rate limit: 10 updates per minute
requireRateLimit('social_credentials_update', 10, 60);

try {
    $input = body_json();
    
    // Required: platform
    $platform = strtolower(trim($input['platform'] ?? ''));
    $validPlatforms = ['instagram', 'twitter', 'facebook', 'discord', 'threads', 'bluesky', 'youtube'];
    
    if (!$platform || !in_array($platform, $validPlatforms)) {
        json_error('Invalid platform. Must be one of: ' . implode(', ', $validPlatforms), 400);
    }
    
    // Action: 'update', 'disconnect', 'test'
    $action = strtolower(trim($input['action'] ?? 'update'));
    
    // Handle disconnect
    if ($action === 'disconnect') {
        $stmt = db()->prepare("
            UPDATE social_api_credentials 
            SET access_token = NULL, refresh_token = NULL, is_active = 0, 
                config = NULL, token_expires_at = NULL
            WHERE platform = ?
        ");
        $stmt->execute([$platform]);
        
        if ($stmt->rowCount() === 0) {
            // No row existed, create disabled one
            $stmt = db()->prepare("
                INSERT INTO social_api_credentials (platform, is_active) 
                VALUES (?, 0)
                ON DUPLICATE KEY UPDATE is_active = 0, access_token = NULL
            ");
            $stmt->execute([$platform]);
        }
        
        json_response([
            'success' => true,
            'message' => ucfirst($platform) . ' disconnected successfully'
        ]);
    }
    
    // Handle test connection
    if ($action === 'test') {
        // Fetch current credentials
        $stmt = db()->prepare("
            SELECT access_token, config FROM social_api_credentials
            WHERE platform = ? AND is_active = 1
        ");
        $stmt->execute([$platform]);
        $creds = $stmt->fetch();

        if (!$creds) {
            json_error('No credentials configured for ' . $platform, 400);
        }

        // Discord uses webhook (config) instead of access token
        if ($platform !== 'discord' && !$creds['access_token']) {
            json_error('No credentials configured for ' . $platform, 400);
        }

        // Test connection based on platform
        $testResult = testPlatformConnection($platform, $creds);

        if ($testResult['success']) {
            // Update last_used_at
            $stmt = db()->prepare("
                UPDATE social_api_credentials
                SET last_used_at = NOW()
                WHERE platform = ?
            ");
            $stmt->execute([$platform]);
        }

        json_response($testResult);
    }
    
    // Handle update credentials
    $accessToken = $input['access_token'] ?? null;
    $refreshToken = $input['refresh_token'] ?? null;
    $tokenExpiresAt = $input['token_expires_at'] ?? null;
    $config = $input['config'] ?? [];
    $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;
    
    // Platform-specific validation
    $validationResult = validatePlatformCredentials($platform, $accessToken, $config);
    if (!$validationResult['valid']) {
        json_error($validationResult['message'], 400);
    }
    
    // Encrypt tokens if provided
    $encryptedAccessToken = $accessToken ? encryptToken($accessToken) : null;
    $encryptedRefreshToken = $refreshToken ? encryptToken($refreshToken) : null;
    
    // JSON encode config
    $configJson = !empty($config) ? json_encode($config) : null;
    
    // Parse expiry date if provided
    $expiryDate = null;
    if ($tokenExpiresAt) {
        $expiryDate = is_numeric($tokenExpiresAt) 
            ? date('Y-m-d H:i:s', $tokenExpiresAt)
            : date('Y-m-d H:i:s', strtotime($tokenExpiresAt));
    }
    
    // Check if platform exists
    $stmt = db()->prepare("SELECT id FROM social_api_credentials WHERE platform = ?");
    $stmt->execute([$platform]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing
        $updateFields = ['is_active = ?', 'updated_at = NOW()'];
        $updateValues = [$isActive ? 1 : 0];
        
        if ($encryptedAccessToken !== null) {
            $updateFields[] = 'access_token = ?';
            $updateValues[] = $encryptedAccessToken;
        }
        
        if ($encryptedRefreshToken !== null) {
            $updateFields[] = 'refresh_token = ?';
            $updateValues[] = $encryptedRefreshToken;
        }
        
        if ($expiryDate !== null) {
            $updateFields[] = 'token_expires_at = ?';
            $updateValues[] = $expiryDate;
        }
        
        if ($configJson !== null) {
            $updateFields[] = 'config = ?';
            $updateValues[] = $configJson;
        }
        
        $updateValues[] = $platform;
        
        $stmt = db()->prepare("
            UPDATE social_api_credentials 
            SET " . implode(', ', $updateFields) . "
            WHERE platform = ?
        ");
        $stmt->execute($updateValues);
    } else {
        // Insert new
        $stmt = db()->prepare("
            INSERT INTO social_api_credentials 
            (platform, access_token, refresh_token, token_expires_at, config, is_active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $platform,
            $encryptedAccessToken,
            $encryptedRefreshToken,
            $expiryDate,
            $configJson,
            $isActive ? 1 : 0
        ]);
    }
    
    json_response([
        'success' => true,
        'message' => ucfirst($platform) . ' credentials updated successfully',
        'platform' => $platform,
        'is_active' => $isActive,
        'expires_at' => $expiryDate
    ]);

} catch (Exception $e) {
    error_log("Update social credentials error: " . $e->getMessage());
    json_error('Failed to update credentials: ' . $e->getMessage(), 500);
}

/**
 * Validate platform-specific credentials
 */
function validatePlatformCredentials(string $platform, ?string $accessToken, array $config): array {
    switch ($platform) {
        case 'instagram':
            // Instagram requires: access_token, facebook_page_id, instagram_user_id
            if (!$accessToken) {
                return ['valid' => false, 'message' => 'Instagram requires an access token'];
            }
            // Config validation is informational - they may be added later
            return ['valid' => true, 'message' => 'Valid'];
            
        case 'twitter':
            // Twitter requires: access_token (OAuth 2.0 Bearer)
            if (!$accessToken) {
                return ['valid' => false, 'message' => 'Twitter requires an access token'];
            }
            return ['valid' => true, 'message' => 'Valid'];
            
        case 'facebook':
            // Facebook requires: access_token, page_id
            if (!$accessToken) {
                return ['valid' => false, 'message' => 'Facebook requires an access token'];
            }
            return ['valid' => true, 'message' => 'Valid'];
            
        case 'discord':
            // Discord only needs webhook_url in config (no access token)
            $webhookUrl = $config['webhook_url'] ?? null;
            if (!$webhookUrl) {
                return ['valid' => false, 'message' => 'Discord requires a webhook URL in config'];
            }
            if (!filter_var($webhookUrl, FILTER_VALIDATE_URL) || 
                strpos($webhookUrl, 'discord.com/api/webhooks/') === false) {
                return ['valid' => false, 'message' => 'Invalid Discord webhook URL format'];
            }
            return ['valid' => true, 'message' => 'Valid'];
            
        case 'threads':
        case 'bluesky':
        case 'youtube':
            // Future platforms - just require access token
            if (!$accessToken) {
                return ['valid' => false, 'message' => ucfirst($platform) . ' requires an access token'];
            }
            return ['valid' => true, 'message' => 'Valid'];
            
        default:
            return ['valid' => false, 'message' => 'Unknown platform'];
    }
}

/**
 * Test connection to a platform
 */
function testPlatformConnection(string $platform, array $creds): array {
    require_once __DIR__ . '/../helpers/encryption.php';
    
    $accessToken = $creds['access_token'] ? decryptToken($creds['access_token']) : null;
    $config = $creds['config'] ? json_decode($creds['config'], true) : [];
    
    switch ($platform) {
        case 'instagram':
            return testInstagramConnection($accessToken, $config);
            
        case 'twitter':
            return testTwitterConnection($accessToken);
            
        case 'facebook':
            return testFacebookConnection($accessToken, $config);
            
        case 'discord':
            return testDiscordConnection($config);
            
        default:
            return ['success' => false, 'message' => 'Testing not implemented for ' . $platform];
    }
}

/**
 * Test Instagram connection
 */
function testInstagramConnection(string $accessToken, array $config): array {
    $igUserId = $config['instagram_user_id'] ?? null;
    
    // Test by fetching user info
    $url = "https://graph.facebook.com/v18.0/me?access_token=" . urlencode($accessToken);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        $errorMsg = $error['error']['message'] ?? 'Unknown error';
        return ['success' => false, 'message' => 'Instagram API error: ' . $errorMsg];
    }
    
    $data = json_decode($response, true);
    return [
        'success' => true,
        'message' => 'Connected to Instagram',
        'account' => $data['name'] ?? 'Unknown'
    ];
}

/**
 * Test Twitter connection
 */
function testTwitterConnection(string $accessToken): array {
    // Test by fetching authenticated user
    $url = "https://api.twitter.com/2/users/me";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $accessToken
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        $errorMsg = $error['detail'] ?? $error['title'] ?? 'Unknown error';
        return ['success' => false, 'message' => 'Twitter API error: ' . $errorMsg];
    }
    
    $data = json_decode($response, true);
    return [
        'success' => true,
        'message' => 'Connected to Twitter',
        'account' => '@' . ($data['data']['username'] ?? 'unknown')
    ];
}

/**
 * Test Facebook connection
 */
function testFacebookConnection(string $accessToken, array $config): array {
    $pageId = $config['page_id'] ?? null;
    
    // Test by fetching page info or user info
    $url = $pageId 
        ? "https://graph.facebook.com/v18.0/{$pageId}?access_token=" . urlencode($accessToken)
        : "https://graph.facebook.com/v18.0/me?access_token=" . urlencode($accessToken);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        $errorMsg = $error['error']['message'] ?? 'Unknown error';
        return ['success' => false, 'message' => 'Facebook API error: ' . $errorMsg];
    }
    
    $data = json_decode($response, true);
    return [
        'success' => true,
        'message' => 'Connected to Facebook',
        'account' => $data['name'] ?? 'Unknown'
    ];
}

/**
 * Test Discord webhook
 */
function testDiscordConnection(array $config): array {
    $webhookUrl = $config['webhook_url'] ?? null;
    
    if (!$webhookUrl) {
        return ['success' => false, 'message' => 'No webhook URL configured'];
    }
    
    // Test by fetching webhook info (GET request)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['success' => false, 'message' => 'Invalid Discord webhook (HTTP ' . $httpCode . ')'];
    }
    
    $data = json_decode($response, true);
    return [
        'success' => true,
        'message' => 'Discord webhook valid',
        'account' => $data['name'] ?? 'Unknown Webhook',
        'channel' => $data['channel_id'] ?? null
    ];
}
