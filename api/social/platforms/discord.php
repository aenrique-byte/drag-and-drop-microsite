<?php
/**
 * Discord Webhook Posting
 * 
 * Posts blog announcements to Discord via webhook.
 * Supports rich embeds with images.
 *
 * @package AuthorCMS
 * @since Phase 4 - Social Media Integration
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../helpers/content-formatter.php';

/**
 * Post to Discord webhook
 *
 * @param array $postData Blog post data
 * @param array $credentials Decrypted credentials with config
 * @return array ['success' => bool, 'platform_post_id' => string|null, 'post_url' => string|null, 'error' => string|null]
 */
function postToDiscord(array $postData, array $credentials): array {
    $config = $credentials['config'] ?? [];
    $webhookUrl = $config['webhook_url'] ?? null;
    
    if (!$webhookUrl) {
        return [
            'success' => false,
            'platform_post_id' => null,
            'post_url' => null,
            'error' => 'Discord webhook URL not configured'
        ];
    }
    
    // Format content for Discord
    $discordContent = formatForDiscord($postData, true);
    
    // Send to Discord webhook
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl . '?wait=true'); // wait=true returns the message
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($discordContent));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
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
            'error' => 'Discord request failed: ' . $curlError
        ];
    }
    
    // Discord returns 200 for success with message, 204 for success without (no wait param)
    if ($httpCode === 200 || $httpCode === 204) {
        $result = $httpCode === 200 ? json_decode($response, true) : [];
        $messageId = $result['id'] ?? null;
        $channelId = $result['channel_id'] ?? null;
        
        // Construct message URL if we have the data
        $postUrl = null;
        if ($messageId && $channelId) {
            // Get guild ID from webhook info in config
            $guildId = $config['guild_id'] ?? null;
            if ($guildId) {
                $postUrl = "https://discord.com/channels/{$guildId}/{$channelId}/{$messageId}";
            }
        }
        
        return [
            'success' => true,
            'platform_post_id' => $messageId,
            'post_url' => $postUrl,
            'error' => null
        ];
    }
    
    // Handle error response
    $errorData = json_decode($response, true);
    $errorMessage = $errorData['message'] ?? 'Unknown Discord error (HTTP ' . $httpCode . ')';

    // Log detailed error for debugging
    error_log("Discord webhook error (HTTP {$httpCode}): " . json_encode([
        'error' => $errorMessage,
        'response' => $response,
        'sent_data' => $discordContent
    ]));

    return [
        'success' => false,
        'platform_post_id' => null,
        'post_url' => null,
        'error' => 'Discord error: ' . $errorMessage . ' (Check server logs for details)'
    ];
}

/**
 * Send a test message to Discord webhook
 *
 * @param string $webhookUrl Discord webhook URL
 * @return array ['success' => bool, 'message' => string]
 */
function testDiscordWebhook(string $webhookUrl): array {
    $testContent = [
        'content' => '🧪 **Test message from Author CMS**',
        'embeds' => [[
            'title' => 'Connection Test',
            'description' => 'This is a test message to verify your Discord webhook is working correctly.',
            'color' => 0x10b981,
            'footer' => ['text' => 'Author CMS - Social Media Integration'],
            'timestamp' => date('c')
        ]]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testContent));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 || $httpCode === 204) {
        return ['success' => true, 'message' => 'Test message sent successfully to Discord'];
    }
    
    return ['success' => false, 'message' => 'Discord webhook test failed (HTTP ' . $httpCode . ')'];
}
