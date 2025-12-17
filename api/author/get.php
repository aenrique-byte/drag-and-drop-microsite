<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT name, bio, tagline, profile_image, background_image_light, background_image_dark, site_domain, gallery_rating_filter, show_litrpg_tools, show_shoutouts, updated_at FROM author_profile LIMIT 1");
    $stmt->execute();
    $profile = $stmt->fetch();

    if (!$profile) {
        // Return default profile if none exists
        $profile = [
            'name' => 'O.C. Wanderer',
            'bio' => 'Sci-Fi & Fantasy Author',
            'tagline' => 'Worlds of adventure, danger, and love',
            'profile_image' => null,
            'background_image_light' => null,
            'background_image_dark' => null,
            'site_domain' => 'authorsite.com',
            'gallery_rating_filter' => 'auto',
            'show_litrpg_tools' => true,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    } else {
        // Convert show_litrpg_tools to boolean (default true if column doesn't exist or is null)
        $profile['show_litrpg_tools'] = isset($profile['show_litrpg_tools']) ? (bool)$profile['show_litrpg_tools'] : true;
        // Convert show_shoutouts to boolean (default false if column doesn't exist or is null)
        $profile['show_shoutouts'] = isset($profile['show_shoutouts']) ? (bool)$profile['show_shoutouts'] : false;
    }

    jsonResponse([
        'success' => true,
        'profile' => $profile
    ]);

} catch (Exception $e) {
    error_log("Author profile get error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Failed to fetch author profile'
    ], 500);
}
?>
