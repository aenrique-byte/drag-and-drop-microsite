<?php
/**
 * Twitter/X API v2 Posting
 * 
 * Posts blog announcements to Twitter/X using OAuth 2.0.
 * Supports text tweets with images.
 *
 * @package AuthorCMS
 * @since Phase 4 - Social Media Integration
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../helpers/content-formatter.php';

/**
 * Post to Twitter/X
 *
 * @param array $postData Blog post data with 'title', 'url', 'tags', 'twitter_image', 'custom_message'
 * @param array $credentials Decrypted credentials with 'access_token'
 * @return array ['success' => bool, 'platform_post_id' => string|null, 'post_url' => string|null, 'error' => string|null]
 */
function postToTwitter(array $postData, array $credentials): array {
    $accessToken = $credentials['access_token'] ?? null;
    
    if (!$accessToken) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Twitter access token not configured'
        ];
    }
    
    // Upload image if provided
    $mediaId = null;
    $imageUrl = $postData['twitter_image'] ?? null;
    
    if ($imageUrl) {
        $mediaResult = uploadTwitterMedia($accessToken, $imageUrl);
        if ($mediaResult['success']) {
            $mediaId = $mediaResult['media_id'];
        } else {
            // Log but don't fail the entire post
            error_log("Twitter media upload failed: " . $mediaResult['error']);
        }
    }
    
    // Format content for Twitter
    $tweetText = formatForTwitter($postData);
    
    // Build tweet request body
    $tweetBody = ['text' => $tweetText];
    
    if ($mediaId) {
        $tweetBody['media'] = ['media_ids' => [$mediaId]];
    }
    
    // Post tweet
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.twitter.com/2/tweets');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tweetBody));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Check for curl errors
    if ($curlError) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Twitter request failed: ' . $curlError
        ];
    }
    
    $responseData = json_decode($response, true);
    
    // Success: 201 Created
    if ($httpCode === 201 && isset($responseData['data']['id'])) {
        $tweetId = $responseData['data']['id'];
        
        // Get username for URL (we might have it cached in config)
        $config = $credentials['config'] ?? [];
        $username = $config['username'] ?? null;
        $postUrl = $username 
            ? "https://twitter.com/{$username}/status/{$tweetId}"
            : "https://twitter.com/i/status/{$tweetId}";
        
        return [
            'success' => true,
            'platform_post_id' => $tweetId,
            'post_url' => $postUrl,
            'error' => null
        ];
    }
    
    // Handle error response
    $errorMessage = 'Unknown Twitter error (HTTP ' . $httpCode . ')';
    
    if (isset($responseData['errors'][0]['message'])) {
        $errorMessage = $responseData['errors'][0]['message'];
    } elseif (isset($responseData['detail'])) {
        $errorMessage = $responseData['detail'];
    } elseif (isset($responseData['title'])) {
        $errorMessage = $responseData['title'];
    }
    
    return [
        'success' => false,
        'platform_post_id' => null,
        'post_url' => null,
        'error' => 'Twitter error: ' . $errorMessage
    ];
}

/**
 * Upload media to Twitter (v1.1 endpoint - still required for media upload)
 *
 * @param string $accessToken OAuth 2.0 Bearer token
 * @param string $imageUrl URL of the image to upload
 * @return array ['success' => bool, 'media_id' => string|null, 'error' => string|null]
 */
function uploadTwitterMedia(string $accessToken, string $imageUrl): array {
    // Download image from URL first
    $imageData = file_get_contents($imageUrl);
    
    if ($imageData === false) {
        return [
            'success' => false,
            'media_id' => null,
            'error' => 'Failed to download image from URL'
        ];
    }
    
    // Check image size (Twitter limit: 5MB for images)
    if (strlen($imageData) > 5 * 1024 * 1024) {
        return [
            'success' => false,
            'media_id' => null,
            'error' => 'Image exceeds Twitter 5MB limit'
        ];
    }
    
    $imageBase64 = base64_encode($imageData);
    
    // Twitter media upload still uses v1.1 endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://upload.twitter.com/1.1/media/upload.json');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['media_data' => $imageBase64]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return [
            'success' => false,
            'media_id' => null,
            'error' => 'Media upload request failed: ' . $curlError
        ];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && isset($responseData['media_id_string'])) {
        return [
            'success' => true,
            'media_id' => $responseData['media_id_string'],
            'error' => null
        ];
    }
    
    $errorMessage = $responseData['errors'][0]['message'] ?? 'Unknown media upload error';
    
    return [
        'success' => false,
        'media_id' => null,
        'error' => 'Media upload failed: ' . $errorMessage
    ];
}

/**
 * Delete a tweet
 *
 * @param string $accessToken OAuth 2.0 Bearer token
 * @param string $tweetId Tweet ID to delete
 * @return array ['success' => bool, 'error' => string|null]
 */
function deleteTwitterPost(string $accessToken, string $tweetId): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.twitter.com/2/tweets/{$tweetId}");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['success' => true, 'error' => null];
    }
    
    $responseData = json_decode($response, true);
    $errorMessage = $responseData['detail'] ?? 'Failed to delete tweet';
    
    return ['success' => false, 'error' => $errorMessage];
}

/**
 * Get authenticated user info
 *
 * @param string $accessToken OAuth 2.0 Bearer token
 * @return array ['success' => bool, 'user' => array|null, 'error' => string|null]
 */
function getTwitterUser(string $accessToken): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.twitter.com/2/users/me');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        return [
            'success' => true,
            'user' => $data['data'] ?? null,
            'error' => null
        ];
    }
    
    return [
        'success' => false,
        'user' => null,
        'error' => 'Failed to get user info'
    ];
}
