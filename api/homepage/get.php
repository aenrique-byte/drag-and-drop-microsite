<?php
/**
 * Homepage API - Get all homepage data
 * Returns: profile, settings, featured_story, stories, activity, tools, socials
 */

require_once '../bootstrap.php';

header('Content-Type: application/json');

try {

    // ========================================================================
    // 1. FETCH AUTHOR PROFILE
    // ========================================================================
    $profileStmt = $pdo->query("
        SELECT
            name, bio, tagline, profile_image,
            background_image, background_image_light, background_image_dark,
            site_domain
        FROM author_profile
        LIMIT 1
    ");
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        $profile = [
            'name' => 'Author Name',
            'bio' => 'Author & Writer',
            'tagline' => 'Stories that captivate',
            'site_domain' => $_SERVER['HTTP_HOST'] ?? 'localhost'
        ];
    }

    // ========================================================================
    // 2. FETCH HOMEPAGE SETTINGS (with fallback defaults, table might not exist)
    // ========================================================================
    $settingsRow = null;
    try {
        $settingsStmt = $pdo->query("SELECT * FROM homepage_settings LIMIT 1");
        $settingsRow = $settingsStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Homepage settings table error: " . $e->getMessage());
        // Table doesn't exist - use defaults
    }

    // Apply fallback defaults for all settings
    $settings = [
        'hero_title' => $settingsRow['hero_title'] ?? 'Step into the worlds of',
        'hero_tagline' => $settingsRow['hero_tagline'] ?? 'Shared Multiverse Portal',
        'hero_description' => $settingsRow['hero_description'] ?? 'Explore stories, universes, and creative tools.',
        'show_featured_story' => $settingsRow['show_featured_story'] ?? true,
        'show_activity_feed' => $settingsRow['show_activity_feed'] ?? true,
        'show_tools_section' => $settingsRow['show_tools_section'] ?? true,
        'newsletter_cta_text' => $settingsRow['newsletter_cta_text'] ?? 'Join the Newsletter',
        'newsletter_url' => $settingsRow['newsletter_url'] ?? '',
        'brand_color' => $settingsRow['brand_color'] ?? '#10b981',
        'brand_color_dark' => $settingsRow['brand_color_dark'] ?? '#10b981',
        'featured_story_id' => $settingsRow['featured_story_id'] ?? null,
    ];

    // ========================================================================
    // 3. FETCH FEATURED STORY (fallback-safe)
    // ========================================================================
    $featuredStory = null;
    $featuredId = $settings['featured_story_id'];

    try {
        // Auto-select featured story if not explicitly set
        if (empty($featuredId)) {
            // Try with is_featured first, fallback to first published story
            try {
                $autoFeaturedStmt = $pdo->query("
                    SELECT id FROM stories WHERE is_featured = true AND status = 'published' LIMIT 1
                ");
                $autoFeatured = $autoFeaturedStmt->fetch(PDO::FETCH_ASSOC);
                if ($autoFeatured) {
                    $featuredId = $autoFeatured['id'];
                }
            } catch (Exception $e) {
                // is_featured column might not exist, get first published story
                $autoFeaturedStmt = $pdo->query("
                    SELECT id FROM stories WHERE status = 'published' ORDER BY id ASC LIMIT 1
                ");
                $autoFeatured = $autoFeaturedStmt->fetch(PDO::FETCH_ASSOC);
                if ($autoFeatured) {
                    $featuredId = $autoFeatured['id'];
                }
            }
        }

        if ($featuredId) {
            // Try full query first, fallback to basic columns
            try {
                $featuredStmt = $pdo->prepare("
                    SELECT
                        id, title, tagline, description, homepage_description, cover_image,
                        genres, external_links, latest_chapter_number, latest_chapter_title, cta_text, slug
                    FROM stories
                    WHERE id = ? AND status = 'published'
                ");
                $featuredStmt->execute([$featuredId]);
                $featuredStory = $featuredStmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Fallback to basic columns if new columns don't exist
                $featuredStmt = $pdo->prepare("
                    SELECT id, title, description, homepage_description, cover_image, slug
                    FROM stories WHERE id = ? AND status = 'published'
                ");
                $featuredStmt->execute([$featuredId]);
                $featuredStory = $featuredStmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($featuredStory) {
                // Parse genres JSON
                $featuredStory['genres'] = isset($featuredStory['genres']) && $featuredStory['genres']
                    ? json_decode($featuredStory['genres'], true)
                    : [];

                // Parse external_links JSON
                $featuredStory['external_links'] = isset($featuredStory['external_links']) && $featuredStory['external_links']
                    ? json_decode($featuredStory['external_links'], true)
                    : [];

                $featuredStory['id'] = (int)$featuredStory['id'];
                $featuredStory['latest_chapter_number'] = isset($featuredStory['latest_chapter_number'])
                    ? (int)$featuredStory['latest_chapter_number']
                    : null;
            }
        }
    } catch (Exception $e) {
        error_log("Featured story error: " . $e->getMessage());
    }

    // ========================================================================
    // 4. FETCH ALL PUBLISHED STORIES (for grid, fallback-safe)
    // ========================================================================
    $stories = [];
    try {
        // Try full query first (with show_on_homepage filter)
        try {
            $storiesStmt = $pdo->query("
                SELECT id, title, tagline, description, homepage_description, cover_image, genres, external_links, cta_text, display_order, slug
                FROM stories 
                WHERE status = 'published' AND (show_on_homepage = true OR show_on_homepage IS NULL)
                ORDER BY display_order ASC, id DESC
            ");
        } catch (Exception $e) {
            // Fallback to basic columns (show_on_homepage column might not exist yet)
            $storiesStmt = $pdo->query("
                SELECT id, title, description, homepage_description, cover_image, slug
                FROM stories WHERE status = 'published' ORDER BY id DESC
            ");
        }
        $stories = $storiesStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($stories as &$story) {
            $story['id'] = (int)$story['id'];
            $story['display_order'] = isset($story['display_order']) ? (int)$story['display_order'] : 0;

            // Parse genres JSON
            $story['genres'] = isset($story['genres']) && $story['genres']
                ? json_decode($story['genres'], true)
                : [];

            // Parse external_links JSON
            $story['external_links'] = isset($story['external_links']) && $story['external_links']
                ? json_decode($story['external_links'], true)
                : [];
        }
        unset($story);
    } catch (Exception $e) {
        error_log("Stories error: " . $e->getMessage());
    }

    // ========================================================================
    // 5. FETCH ACTIVITY FEED (table might not exist) + BLOG POSTS
    // ========================================================================
    $activity = [];
    
    // Fetch from activity_feed table
    try {
        $activityStmt = $pdo->query("
            SELECT id, type, source, label, title, series_title, url, published_at
            FROM activity_feed WHERE is_active = true
            ORDER BY published_at DESC LIMIT 20
        ");
        $activityItems = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($activityItems as &$item) {
            $item['id'] = (int)$item['id'];
            $item['time_ago'] = timeAgo($item['published_at']);
        }
        unset($item);
        $activity = array_merge($activity, $activityItems);
    } catch (Exception $e) {
        error_log("Activity feed error: " . $e->getMessage());
        // Continue - table might not exist
    }
    
    // Fetch published blog posts and merge into activity
    try {
        $blogStmt = $pdo->query("
            SELECT id, slug, title, excerpt, published_at
            FROM blog_posts 
            WHERE status = 'published' AND published_at IS NOT NULL
            ORDER BY published_at DESC 
            LIMIT 10
        ");
        $blogPosts = $blogStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($blogPosts as $post) {
            $activity[] = [
                'id' => 'blog_' . $post['id'],  // Prefix to avoid ID collision
                'type' => 'blog',
                'source' => 'Blog',
                'label' => 'Blog Post',
                'title' => $post['title'],
                'series_title' => null,
                'url' => '/blog/' . $post['slug'],
                'published_at' => $post['published_at'],
                'time_ago' => timeAgo($post['published_at']),
            ];
        }
    } catch (Exception $e) {
        error_log("Blog posts for activity error: " . $e->getMessage());
        // Continue - blog_posts table might not exist yet
    }
    
    // Sort merged activity by published_at (most recent first)
    usort($activity, function($a, $b) {
        return strtotime($b['published_at']) - strtotime($a['published_at']);
    });
    
    // Limit to 10 most recent items
    $activity = array_slice($activity, 0, 10);

    // ========================================================================
    // 6. FETCH TOOLS (table might not exist)
    // ========================================================================
    $tools = [];
    try {
        $toolsStmt = $pdo->query("
            SELECT id, title, description, icon, link
            FROM homepage_tools WHERE is_active = true
            ORDER BY display_order ASC
        ");
        $tools = $toolsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tools as &$tool) {
            $tool['id'] = (int)$tool['id'];
        }
        unset($tool);
    } catch (Exception $e) {
        error_log("Tools error: " . $e->getMessage());
        // Return empty array if table doesn't exist
    }

    // ========================================================================
    // 7. FETCH SOCIAL LINKS (table is called 'socials' with key_name, url columns)
    // ========================================================================
    $socials = [];
    try {
        $socialsStmt = $pdo->query("SELECT key_name, url FROM socials");
        $socialsRows = $socialsStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($socialsRows as $row) {
            $socials[$row['key_name']] = $row['url'];
        }
    } catch (Exception $e) {
        error_log("Socials error: " . $e->getMessage());
    }

    // ========================================================================
    // 8. BUILD RESPONSE
    // ========================================================================
    $response = [
        'success' => true,
        'profile' => $profile,
        'settings' => $settings,
        'featured_story' => $featuredStory,
        'stories' => $stories,
        'activity' => $activity,
        'tools' => $tools,
        'socials' => $socials,
    ];

    jsonResponse($response);

} catch (Exception $e) {
    error_log("Homepage get error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Failed to fetch homepage data',
        'debug' => $e->getMessage()
    ], 500);
}

/**
 * Calculate human-readable time ago
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}
