<?php
/**
 * Instagram Graph API Posting
 * 
 * Posts blog announcements to Instagram via Facebook Graph API.
 * Requires Instagram Business Account connected to a Facebook Page.
 *
 * IMPORTANT: Instagram API requires PUBLICLY ACCESSIBLE image URLs.
 * - Cannot test from localhost
 * - Images must be served via HTTPS
 * - Self-signed SSL certificates will fail
 *
 * @package AuthorCMS
 * @since Phase 4 - Social Media Integration
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../helpers/content-formatter.php';

/**
 * Post to Instagram
 *
 * Instagram posting is a two-step process:
 * 1. Create a media container with the image URL
 * 2. Publish the container
 *
 * @param array $postData Blog post data with 'title', 'excerpt', 'tags', 'instagram_image', 'custom_message'
 * @param array $credentials Decrypted credentials with 'access_token' and config['instagram_user_id']
 * @return array ['success' => bool, 'platform_post_id' => string|null, 'post_url' => string|null, 'error' => string|null]
 */
function postToInstagram(array $postData, array $credentials): array {
    $accessToken = $credentials['access_token'] ?? null;
    $config = $credentials['config'] ?? [];
    $igUserId = $config['instagram_user_id'] ?? null;
    
    if (!$accessToken) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Instagram access token not configured'
        ];
    }
    
    if (!$igUserId) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Instagram User ID not configured. Connect your Instagram Business Account first.'
        ];
    }
    
    // Get image URL
    $imageUrl = $postData['instagram_image'] ?? null;
    
    if (!$imageUrl) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Instagram requires an image. Please upload an Instagram image (1080x1080 or 1080x1350).'
        ];
    }
    
    // Verify image is publicly accessible
    $accessCheck = verifyImageAccessible($imageUrl);
    if (!$accessCheck['accessible']) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => $accessCheck['error']
        ];
    }
    
    // Format caption
    $caption = formatForInstagram($postData);
    
    // Step 1: Create media container
    $containerResult = createInstagramMediaContainer($igUserId, $accessToken, $imageUrl, $caption);
    
    if (!$containerResult['success']) {
        return $containerResult;
    }
    
    $creationId = $containerResult['creation_id'];
    
    // Wait for media processing (Instagram requires this)
    sleep(2);
    
    // Step 2: Check container status
    $statusResult = checkInstagramMediaStatus($creationId, $accessToken);
    
    if (!$statusResult['ready']) {
        // Try waiting a bit more
        sleep(5);
        $statusResult = checkInstagramMediaStatus($creationId, $accessToken);
        
        if (!$statusResult['ready']) {
            return [
                'success' => false,
                'platform_post_id' => null,
                'post_url' => null,
                'error' => 'Instagram media processing timed out. Status: ' . ($statusResult['status'] ?? 'unknown')
            ];
        }
    }
    
    // Step 3: Publish media
    $publishResult = publishInstagramMedia($igUserId, $accessToken, $creationId);
    
    return $publishResult;
}

/**
 * Create Instagram media container
 *
 * @param string $igUserId Instagram User ID
 * @param string $accessToken Access token
 * @param string $imageUrl Public HTTPS image URL
 * @param string $caption Post caption
 * @return array
 */
function createInstagramMediaContainer(string $igUserId, string $accessToken, string $imageUrl, string $caption): array {
    $url = "https://graph.facebook.com/v18.0/{$igUserId}/media";
    
    $params = [
        'image_url' => $imageUrl,
        'caption' => $caption,
        'access_token' => $accessToken
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'creation_id' => null,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Instagram request failed: ' . $curlError
        ];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['id'])) {
        return [
            'success' => true,
            'creation_id' => $responseData['id'],
            'platform_post_id' => null,
            'post_url' => null,
            'error' => null
        ];
    }
    
    $errorMessage = $responseData['error']['message'] ?? 'Unknown Instagram error';
    $errorCode = $responseData['error']['code'] ?? null;
    
    // Provide helpful error messages
    if ($errorCode === 36003) {
        $errorMessage = 'Image URL not accessible. Instagram requires publicly accessible HTTPS URLs.';
    } elseif ($errorCode === 36000) {
        $errorMessage = 'Invalid image dimensions. Instagram requires 1080x1080 (square) or 1080x1350 (portrait).';
    }
    
    return [
        'success' => false,
        'creation_id' => null,
        'platform_post_id' => null,
        'post_url' => null,
        'error' => 'Instagram container error: ' . $errorMessage
    ];
}

/**
 * Check Instagram media container status
 *
 * @param string $creationId Container ID
 * @param string $accessToken Access token
 * @return array ['ready' => bool, 'status' => string]
 */
function checkInstagramMediaStatus(string $creationId, string $accessToken): array {
    $url = "https://graph.facebook.com/v18.0/{$creationId}?fields=status_code&access_token=" . urlencode($accessToken);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['ready' => false, 'status' => 'error'];
    }
    
    $data = json_decode($response, true);
    $status = $data['status_code'] ?? 'unknown';
    
    // FINISHED = ready to publish
    // IN_PROGRESS = still processing
    // ERROR = failed
    return [
        'ready' => $status === 'FINISHED',
        'status' => $status
    ];
}

/**
 * Publish Instagram media container
 *
 * @param string $igUserId Instagram User ID
 * @param string $accessToken Access token
 * @param string $creationId Container ID
 * @return array
 */
function publishInstagramMedia(string $igUserId, string $accessToken, string $creationId): array {
    $url = "https://graph.facebook.com/v18.0/{$igUserId}/media_publish";
    
    $params = [
        'creation_id' => $creationId,
        'access_token' => $accessToken
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Instagram publish request failed: ' . $curlError
        ];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['id'])) {
        $mediaId = $responseData['id'];
        
        // Get permalink
        $permalink = getInstagramPermalink($mediaId, $accessToken);
        
        return [
            'success' => true,
            'platform_post_id' => $mediaId,
            'post_url' => $permalink,
            'error' => null
        ];
    }
    
    $errorMessage = $responseData['error']['message'] ?? 'Unknown Instagram publish error';
    
    return [
        'success' => false,
        'platform_post_id' => null,
        'post_url' => null,
        'error' => 'Instagram publish error: ' . $errorMessage
    ];
}

/**
 * Get Instagram post permalink
 *
 * @param string $mediaId Media ID
 * @param string $accessToken Access token
 * @return string|null Permalink URL or null
 */
function getInstagramPermalink(string $mediaId, string $accessToken): ?string {
    $url = "https://graph.facebook.com/v18.0/{$mediaId}?fields=permalink&access_token=" . urlencode($accessToken);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return $data['permalink'] ?? null;
    }
    
    return null;
}

/**
 * Verify image is publicly accessible via HTTPS
 *
 * @param string $imageUrl URL to verify
 * @return array ['accessible' => bool, 'error' => string|null]
 */
function verifyImageAccessible(string $imageUrl): array {
    // Must be HTTPS
    if (strpos($imageUrl, 'https://') !== 0) {
        return [
            'accessible' => false,
            'error' => 'Instagram requires HTTPS image URLs. Current URL uses: ' . parse_url($imageUrl, PHP_URL_SCHEME)
        ];
    }
    
    // Must not be localhost or private IP
    $host = parse_url($imageUrl, PHP_URL_HOST);
    if ($host === 'localhost' || $host === '127.0.0.1' || strpos($host, '192.168.') === 0 || strpos($host, '10.') === 0) {
        return [
            'accessible' => false,
            'error' => 'Instagram cannot access localhost or private network images. Use a public domain.'
        ];
    }
    
    // Check if URL is accessible
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $imageUrl);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'accessible' => false,
            'error' => 'Cannot access image URL: ' . $curlError
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'accessible' => false,
            'error' => "Image URL returned HTTP {$httpCode}. Instagram requires HTTP 200."
        ];
    }
    
    // Verify it's actually an image
    if ($contentType && strpos($contentType, 'image/') !== 0) {
        return [
            'accessible' => false,
            'error' => "URL does not return an image. Content-Type: {$contentType}"
        ];
    }
    
    return ['accessible' => true, 'error' => null];
}

/**
 * Get Instagram Business Account info
 *
 * @param string $accessToken Facebook access token
 * @return array
 */
function getInstagramAccountInfo(string $accessToken): array {
    // First get Facebook pages
    $pagesUrl = "https://graph.facebook.com/v18.0/me/accounts?fields=id,name,instagram_business_account{id,username}&access_token=" . urlencode($accessToken);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $pagesUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        return [
            'success' => false,
            'accounts' => [],
            'error' => $error['error']['message'] ?? 'Failed to get Facebook pages'
        ];
    }
    
    $data = json_decode($response, true);
    $pages = $data['data'] ?? [];
    
    // Extract Instagram accounts
    $igAccounts = [];
    foreach ($pages as $page) {
        if (isset($page['instagram_business_account'])) {
            $igAccounts[] = [
                'facebook_page_id' => $page['id'],
                'facebook_page_name' => $page['name'],
                'instagram_user_id' => $page['instagram_business_account']['id'],
                'instagram_username' => $page['instagram_business_account']['username'] ?? null
            ];
        }
    }
    
    return [
        'success' => true,
        'accounts' => $igAccounts,
        'error' => null
    ];
}
