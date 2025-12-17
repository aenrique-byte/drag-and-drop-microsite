<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

// GET - Fetch current settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM homepage_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Always return defaults if no settings exist
        if (!$settings) {
            $settings = [
                'id' => null,
                'hero_title' => 'Step into the worlds of',
                'hero_tagline' => 'Shared Multiverse Portal',
                'hero_description' => 'Starships, sky-pirates, cursed knights, and reluctant warlocks.',
                'featured_story_id' => null,
                'show_featured_story' => true,
                'show_activity_feed' => true,
                'show_tools_section' => true,
                'newsletter_cta_text' => 'Join the Newsletter',
                'newsletter_url' => '',
                'brand_color' => '#10b981',
                'brand_color_dark' => '#10b981'
            ];
        } else {
            // Convert boolean fields
            $settings['show_featured_story'] = (bool)$settings['show_featured_story'];
            $settings['show_activity_feed'] = (bool)$settings['show_activity_feed'];
            $settings['show_tools_section'] = (bool)$settings['show_tools_section'];
            $settings['featured_story_id'] = $settings['featured_story_id'] ? (int)$settings['featured_story_id'] : null;
        }
        
        jsonResponse(['success' => true, 'settings' => $settings]);
    } catch (Exception $e) {
        error_log("Settings GET error: " . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Failed to fetch settings'], 500);
    }
    exit;
}

// POST - Update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validation
        $errors = [];
        if (empty($data['hero_title'])) $errors[] = 'Hero title is required';
        if (empty($data['hero_tagline'])) $errors[] = 'Hero tagline is required';
        if (empty($data['newsletter_cta_text'])) $errors[] = 'Newsletter CTA text is required';
        if (empty($data['brand_color'])) {
            $errors[] = 'Brand color (light) is required';
        } elseif (!preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $data['brand_color'])) {
            $errors[] = 'Brand color (light) must be a valid hex color';
        }
        if (empty($data['brand_color_dark'])) {
            $errors[] = 'Brand color (dark) is required';
        } elseif (!preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $data['brand_color_dark'])) {
            $errors[] = 'Brand color (dark) must be a valid hex color';
        }

        if (!empty($data['newsletter_url']) && !preg_match('/^https?:\/\/.+/i', $data['newsletter_url'])) {
            $errors[] = 'Newsletter URL must start with http:// or https://';
        }
        
        if (!empty($errors)) {
            jsonResponse(['success' => false, 'error' => implode('. ', $errors)], 400);
        }
        
        // Prepare data
        $hero_title = $data['hero_title'];
        $hero_tagline = $data['hero_tagline'];
        $hero_description = $data['hero_description'] ?? '';
        $featured_story_id = !empty($data['featured_story_id']) ? (int)$data['featured_story_id'] : null;
        $show_featured_story = isset($data['show_featured_story']) ? ($data['show_featured_story'] ? 1 : 0) : 1;
        $show_activity_feed = isset($data['show_activity_feed']) ? ($data['show_activity_feed'] ? 1 : 0) : 1;
        $show_tools_section = isset($data['show_tools_section']) ? ($data['show_tools_section'] ? 1 : 0) : 1;
        $newsletter_cta_text = $data['newsletter_cta_text'];
        $newsletter_url = $data['newsletter_url'] ?? '';
        $brand_color = $data['brand_color'];
        $brand_color_dark = $data['brand_color_dark'];

        // Check if settings exist
        $checkStmt = $pdo->query("SELECT id FROM homepage_settings LIMIT 1");
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing
            $stmt = $pdo->prepare("UPDATE homepage_settings SET
                hero_title = ?, hero_tagline = ?, hero_description = ?,
                featured_story_id = ?, show_featured_story = ?, show_activity_feed = ?,
                show_tools_section = ?, newsletter_cta_text = ?, newsletter_url = ?,
                brand_color = ?, brand_color_dark = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([
                $hero_title, $hero_tagline, $hero_description,
                $featured_story_id, $show_featured_story, $show_activity_feed,
                $show_tools_section, $newsletter_cta_text, $newsletter_url,
                $brand_color, $brand_color_dark, $existing['id']
            ]);
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO homepage_settings
                (hero_title, hero_tagline, hero_description, featured_story_id, show_featured_story,
                show_activity_feed, show_tools_section, newsletter_cta_text, newsletter_url, brand_color, brand_color_dark)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $hero_title, $hero_tagline, $hero_description,
                $featured_story_id, $show_featured_story, $show_activity_feed,
                $show_tools_section, $newsletter_cta_text, $newsletter_url, $brand_color, $brand_color_dark
            ]);
        }
        
        jsonResponse(['success' => true, 'message' => 'Settings updated successfully']);
    } catch (Exception $e) {
        error_log("Settings POST error: " . $e->getMessage());
        jsonResponse(['success' => false, 'error' => 'Failed to update settings'], 500);
    }
    exit;
}

jsonResponse(['error' => 'Method not allowed'], 405);
