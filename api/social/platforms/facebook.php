<?php
/**
 * Facebook Graph API Posting
 * 
 * Posts blog announcements to Facebook Page via Graph API.
 * Supports image posts with link previews.
 *
 * @package AuthorCMS
 * @since Phase 4 - Social Media Integration
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../helpers/content-formatter.php';

/**
 * Post to Facebook Page
 *
 * @param array $postData Blog post data with 'title', 'excerpt', 'url', 'facebook_image', 'custom_message'
 * @param array $credentials Decrypted credentials with 'access_token' and config['page_id']
 * @return array ['success' => bool, 'platform_post_id' => string|null, 'post_url' => string|null, 'error' => string|null]
 */
function postToFacebook(array $postData, array $credentials): array {
    $accessToken = $credentials['access_token'] ?? null;
    $config = $credentials['config'] ?? [];
    $pageId = $config['page_id'] ?? null;
    
    if (!$accessToken) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Facebook access token not configured'
        ];
    }
    
    if (!$pageId) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Facebook Page ID not configured'
        ];
    }
    
    // Format content for Facebook
    $message = formatForFacebook($postData);
    
    // Determine post type: photo post or link post
    $imageUrl = $postData['facebook_image'] ?? $postData['cover_image'] ?? null;
    $postUrl = $postData['url'] ?? null;
    
    // Choose endpoint based on content
    if ($imageUrl) {
        // Photo post with message
        $result = postFacebookPhoto($pageId, $accessToken, $message, $imageUrl);
    } else {
        // Link post (Facebook will auto-generate preview)
        $result = postFacebookLink($pageId, $accessToken, $message, $postUrl);
    }
    
    return $result;
}

/**
 * Post a photo to Facebook Page
 *
 * @param string $pageId Facebook Page ID
 * @param string $accessToken Page access token
 * @param string $message Post caption
 * @param string $imageUrl URL of image (must be publicly accessible)
 * @return array
 */
function postFacebookPhoto(string $pageId, string $accessToken, string $message, string $imageUrl): array {
    // Facebook requires publicly accessible HTTPS image URL
    if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Invalid image URL'
        ];
    }
    
    $params = [
        'url' => $imageUrl,
        'caption' => $message,
        'access_token' => $accessToken,
        'published' => 'true'
    ];
    
    $url = "https://graph.facebook.com/v18.0/{$pageId}/photos";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Facebook request failed: ' . $curlError
        ];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['id'])) {
        $photoId = $responseData['id'];
        $postId = $responseData['post_id'] ?? $photoId;
        
        return [
            'success' => true,
            'platform_post_id' => $postId,
            'post_url' => "https://www.facebook.com/{$pageId}/posts/{$postId}",
            'error' => null
        ];
    }
    
    $errorMessage = $responseData['error']['message'] ?? 'Unknown Facebook error';
    
    return [
        'success' => false,
        'platform_post_id' => null,
        'post_url' => null,
        'error' => 'Facebook photo error: ' . $errorMessage
    ];
}

/**
 * Post a link to Facebook Page (with auto-generated preview)
 *
 * @param string $pageId Facebook Page ID
 * @param string $accessToken Page access token
 * @param string $message Post message
 * @param string|null $link URL to share
 * @return array
 */
function postFacebookLink(string $pageId, string $accessToken, string $message, ?string $link = null): array {
    $params = [
        'message' => $message,
        'access_token' => $accessToken
    ];
    
    if ($link) {
        $params['link'] = $link;
    }
    
    $url = "https://graph.facebook.com/v18.0/{$pageId}/feed";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
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
            'error' => 'Facebook request failed: ' . $curlError
        ];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['id'])) {
        $postId = $responseData['id'];
        // Post ID format is usually "pageId_postId", extract just the post part
        $postIdParts = explode('_', $postId);
        $postIdSimple = end($postIdParts);
        
        return [
            'success' => true,
            'platform_post_id' => $postId,
            'post_url' => "https://www.facebook.com/{$pageId}/posts/{$postIdSimple}",
            'error' => null
        ];
    }
    
    $errorMessage = $responseData['error']['message'] ?? 'Unknown Facebook error';
    
    return [
        'success' => false,
        'platform_post_id' => null,
        'post_url' => null,
        'error' => 'Facebook post error: ' . $errorMessage
    ];
}

/**
 * Delete a Facebook post
 *
 * @param string $accessToken Page access token
 * @param string $postId Post ID to delete
 * @return array ['success' => bool, 'error' => string|null]
 */
function deleteFacebookPost(string $accessToken, string $postId): array {
    $url = "https://graph.facebook.com/v18.0/{$postId}?access_token=" . urlencode($accessToken);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if (isset($data['success']) && $data['success']) {
            return ['success' => true, 'error' => null];
        }
    }
    
    $responseData = json_decode($response, true);
    $errorMessage = $responseData['error']['message'] ?? 'Failed to delete Facebook post';
    
    return ['success' => false, 'error' => $errorMessage];
}

/**
 * Get Facebook Page info
 *
 * @param string $accessToken Access token
 * @param string|null $pageId Page ID (optional, fetches all pages if null)
 * @return array
 */
function getFacebookPageInfo(string $accessToken, ?string $pageId = null): array {
    if ($pageId) {
        $url = "https://graph.facebook.com/v18.0/{$pageId}?fields=id,name,fan_count,followers_count&access_token=" . urlencode($accessToken);
    } else {
        // Get all pages the user manages
        $url = "https://graph.facebook.com/v18.0/me/accounts?fields=id,name,access_token&access_token=" . urlencode($accessToken);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'data' => $pageId ? $data : ($data['data'] ?? []),
            'error' => null
        ];
    }
    
    $responseData = json_decode($response, true);
    return [
        'success' => false,
        'data' => null,
        'error' => $responseData['error']['message'] ?? 'Failed to get page info'
    ];
}

/**
 * Verify image is accessible (important for Facebook photo posts)
 *
 * @param string $imageUrl URL to verify
 * @return array ['accessible' => bool, 'error' => string|null]
 */
function verifyFacebookImageAccessible(string $imageUrl): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $imageUrl);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return [
            'accessible' => false,
            'error' => "Image not accessible (HTTP {$httpCode}). Facebook requires publicly accessible HTTPS URLs."
        ];
    }
    
    // Check content type
    if (strpos($contentType, 'image/') !== 0) {
        return [
            'accessible' => false,
            'error' => "URL does not return an image (Content-Type: {$contentType})"
        ];
    }
    
    return ['accessible' => true, 'error' => null];
}
