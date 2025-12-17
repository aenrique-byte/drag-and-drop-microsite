<?php
/**
 * Social Media Content Formatter
 * 
 * Platform-specific content formatting with character limits,
 * hashtag optimization, and link handling.
 *
 * @package AuthorCMS
 * @since Phase 4 - Social Media Integration
 */

/**
 * Format content for Instagram
 * 
 * Instagram caption requirements:
 * - Max 2200 characters (first 125 visible before "more")
 * - Max 30 hashtags
 * - No clickable links in caption (mention "link in bio")
 *
 * @param array $post Blog post data
 * @return string Formatted Instagram caption
 */
function formatForInstagram(array $post): string {
    $title = $post['title'] ?? '';
    $excerpt = $post['excerpt'] ?? '';
    $tags = $post['tags'] ?? [];
    $customMessage = $post['custom_message'] ?? null;
    
    // Use custom message if provided, otherwise build from title + excerpt
    if ($customMessage) {
        $caption = $customMessage;
    } else {
        $caption = $title;
        if ($excerpt) {
            $caption .= "\n\n" . $excerpt;
        }
    }
    
    // Add call to action for bio link
    $cta = "\n\n🔗 Link in bio to read more!";
    
    // Process tags - Instagram uses # with no spaces
    $hashtags = [];
    if (!empty($tags)) {
        // Limit to 30 hashtags
        $tagSlice = array_slice($tags, 0, 30);
        foreach ($tagSlice as $tag) {
            // Remove spaces and special characters, make camelCase
            $cleanTag = preg_replace('/[^a-zA-Z0-9]/', '', $tag);
            if ($cleanTag) {
                $hashtags[] = '#' . $cleanTag;
            }
        }
    }
    
    $hashtagString = $hashtags ? "\n\n" . implode(' ', $hashtags) : '';
    
    // Combine and truncate to 2200 chars
    $fullCaption = $caption . $cta . $hashtagString;
    
    if (mb_strlen($fullCaption) > 2200) {
        // Truncate caption, keep hashtags
        $availableLength = 2200 - mb_strlen($cta . $hashtagString) - 3;
        $caption = mb_substr($caption, 0, $availableLength) . '...';
        $fullCaption = $caption . $cta . $hashtagString;
    }
    
    return $fullCaption;
}

/**
 * Format content for Twitter/X
 * 
 * Twitter requirements:
 * - Max 280 characters (links auto-shortened to 23 chars by t.co)
 * - Up to 4 images per tweet
 *
 * @param array $post Blog post data
 * @return string Formatted tweet text
 */
function formatForTwitter(array $post): string {
    $title = $post['title'] ?? '';
    $url = $post['url'] ?? '';
    $tags = $post['tags'] ?? [];
    $customMessage = $post['custom_message'] ?? null;
    
    // Twitter auto-shortens URLs to 23 characters
    $urlLength = $url ? 23 : 0;
    
    // Use custom message if provided
    if ($customMessage) {
        $text = $customMessage;
    } else {
        $text = $title;
    }
    
    // Add top hashtags (limit to 2-3 for Twitter)
    $hashtags = [];
    if (!empty($tags)) {
        $tagSlice = array_slice($tags, 0, 3);
        foreach ($tagSlice as $tag) {
            $cleanTag = preg_replace('/[^a-zA-Z0-9]/', '', $tag);
            if ($cleanTag) {
                $hashtags[] = '#' . $cleanTag;
            }
        }
    }
    
    $hashtagString = $hashtags ? ' ' . implode(' ', $hashtags) : '';
    
    // Calculate available space for text
    // Format: text + hashtags + newline + url = 280
    $availableForText = 280 - mb_strlen($hashtagString) - $urlLength - 2; // 2 for newlines
    
    // Truncate text if necessary
    if (mb_strlen($text) > $availableForText) {
        $text = mb_substr($text, 0, $availableForText - 3) . '...';
    }
    
    // Build final tweet
    $parts = [$text];
    if ($hashtagString) {
        $parts[0] .= $hashtagString;
    }
    if ($url) {
        $parts[] = $url;
    }
    
    return implode("\n\n", $parts);
}

/**
 * Format content for Facebook
 * 
 * Facebook requirements:
 * - Very generous character limit (63,206)
 * - Link previews work automatically
 * - Hashtags are not as important
 *
 * @param array $post Blog post data
 * @return string Formatted Facebook post
 */
function formatForFacebook(array $post): string {
    $title = $post['title'] ?? '';
    $excerpt = $post['excerpt'] ?? '';
    $url = $post['url'] ?? '';
    $customMessage = $post['custom_message'] ?? null;
    
    // Use custom message if provided
    if ($customMessage) {
        $text = $customMessage;
    } else {
        // Build engaging post
        $text = "📖 " . $title;
        if ($excerpt) {
            $text .= "\n\n" . $excerpt;
        }
    }
    
    // Add URL (Facebook will auto-generate preview)
    if ($url) {
        $text .= "\n\n🔗 Read more: " . $url;
    }
    
    // Truncate if somehow exceeds limit (unlikely)
    if (mb_strlen($text) > 63000) {
        $text = mb_substr($text, 0, 62997) . '...';
    }
    
    return $text;
}

/**
 * Format content for Discord webhook
 * 
 * Discord requirements:
 * - Max 2000 characters for regular messages
 * - Rich embeds supported for better formatting
 *
 * @param array $post Blog post data
 * @param bool $asEmbed Whether to return embed format
 * @return array|string Formatted Discord content (embed array or string)
 */
function formatForDiscord(array $post, bool $asEmbed = true): array|string {
    $title = $post['title'] ?? '';
    $excerpt = $post['excerpt'] ?? '';
    $url = $post['url'] ?? '';
    $coverImage = $post['cover_image'] ?? null;
    $authorName = $post['author_name'] ?? 'Author';
    $customMessage = $post['custom_message'] ?? null;
    
    if ($asEmbed) {
        // Ensure we have a description (Discord requires it)
        $description = $customMessage ?: $excerpt;
        if (empty($description)) {
            $description = 'Check out this new blog post!';
        }

        // Rich embed format
        $embed = [
            'title' => $title ?: 'New Blog Post',
            'description' => $description,
            'url' => $url,
            'color' => 0x10b981, // Emerald brand color
            'footer' => [
                'text' => '📚 New blog post from ' . $authorName
            ],
            'timestamp' => date('c')
        ];

        // Add cover image if available (must be valid URL)
        if ($coverImage && filter_var($coverImage, FILTER_VALIDATE_URL)) {
            $embed['image'] = ['url' => $coverImage];
        }

        return [
            'content' => '📖 **New Blog Post!**',
            'embeds' => [$embed]
        ];
    }
    
    // Plain text format
    $text = "📖 **{$title}**\n\n";
    $text .= $customMessage ?: $excerpt;
    $text .= "\n\n🔗 " . $url;
    
    // Truncate to Discord's limit
    if (mb_strlen($text) > 2000) {
        $text = mb_substr($text, 0, 1997) . '...';
    }
    
    return $text;
}

/**
 * Format content for a specific platform
 *
 * @param string $platform Platform name (instagram, twitter, facebook, discord)
 * @param array $post Blog post data
 * @return array|string Formatted content for the platform
 */
function formatForPlatform(string $platform, array $post): array|string {
    switch (strtolower($platform)) {
        case 'instagram':
            return formatForInstagram($post);
        case 'twitter':
            return formatForTwitter($post);
        case 'facebook':
            return formatForFacebook($post);
        case 'discord':
            return formatForDiscord($post, true);
        default:
            throw new InvalidArgumentException("Unknown platform: {$platform}");
    }
}

/**
 * Validate image dimensions for a platform
 *
 * @param string $platform Platform name
 * @param int $width Image width
 * @param int $height Image height
 * @return array ['valid' => bool, 'message' => string]
 */
function validatePlatformImageDimensions(string $platform, int $width, int $height): array {
    switch (strtolower($platform)) {
        case 'instagram':
            // Instagram: 1080x1080 (square) or 1080x1350 (portrait 4:5)
            $isSquare = $width === 1080 && $height === 1080;
            $isPortrait = $width === 1080 && $height === 1350;
            $isValidRatio = abs(($width / $height) - 1.0) < 0.01 || abs(($width / $height) - 0.8) < 0.01;
            
            if ($isSquare || $isPortrait || $isValidRatio) {
                return ['valid' => true, 'message' => 'Image dimensions valid for Instagram'];
            }
            return [
                'valid' => false, 
                'message' => 'Instagram requires 1080x1080 (square) or 1080x1350 (4:5 portrait) images'
            ];
            
        case 'twitter':
            // Twitter: 1200x675 (16:9 landscape recommended)
            $ratio = $width / $height;
            if ($width >= 600 && $height >= 335 && $ratio > 1.0) {
                return ['valid' => true, 'message' => 'Image dimensions valid for Twitter'];
            }
            return [
                'valid' => false, 
                'message' => 'Twitter requires landscape images, 1200x675 (16:9) recommended'
            ];
            
        case 'facebook':
            // Facebook: 1200x630 (1.91:1 ratio)
            $ratio = $width / $height;
            if ($width >= 600 && $height >= 315 && $ratio > 1.0) {
                return ['valid' => true, 'message' => 'Image dimensions valid for Facebook'];
            }
            return [
                'valid' => false, 
                'message' => 'Facebook requires landscape images, 1200x630 (1.91:1) recommended'
            ];
            
        case 'discord':
            // Discord: Very flexible, almost any dimensions work
            if ($width >= 100 && $height >= 100) {
                return ['valid' => true, 'message' => 'Image dimensions valid for Discord'];
            }
            return [
                'valid' => false, 
                'message' => 'Image must be at least 100x100 pixels'
            ];
            
        default:
            return ['valid' => true, 'message' => 'Unknown platform, skipping validation'];
    }
}

/**
 * Get recommended image dimensions for a platform
 *
 * @param string $platform Platform name
 * @return array ['width' => int, 'height' => int, 'description' => string]
 */
function getPlatformImageRequirements(string $platform): array {
    $requirements = [
        'instagram' => [
            'width' => 1080,
            'height' => 1080,
            'alternatives' => ['1080x1080 (square)', '1080x1350 (portrait 4:5)'],
            'description' => 'Square (1:1) or portrait (4:5) format. 1080px width recommended.'
        ],
        'twitter' => [
            'width' => 1200,
            'height' => 675,
            'alternatives' => ['1200x675 (16:9)'],
            'description' => 'Landscape 16:9 format. 1200x675 pixels recommended.'
        ],
        'facebook' => [
            'width' => 1200,
            'height' => 630,
            'alternatives' => ['1200x630 (1.91:1)'],
            'description' => 'Landscape 1.91:1 format. 1200x630 pixels recommended.'
        ],
        'discord' => [
            'width' => 1280,
            'height' => 720,
            'alternatives' => ['Any dimensions'],
            'description' => 'Discord is flexible. 1280x720 or larger recommended.'
        ]
    ];
    
    return $requirements[strtolower($platform)] ?? [
        'width' => 1200,
        'height' => 630,
        'alternatives' => ['Unknown'],
        'description' => 'Unknown platform'
    ];
}
