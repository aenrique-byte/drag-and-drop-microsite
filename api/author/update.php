<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check authentication (session already started in bootstrap.php)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Validate gallery_rating_filter if provided
if (isset($input['gallery_rating_filter']) && !in_array($input['gallery_rating_filter'], ['always', 'auto', 'never'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid gallery_rating_filter value. Must be: always, auto, or never']);
    exit;
}

try {
    // Get current profile or create if doesn't exist
    $stmt = $pdo->prepare("SELECT id FROM author_profile LIMIT 1");
    $stmt->execute();
    $profile = $stmt->fetch();

    // Convert show_litrpg_tools to integer for database storage
    $showLitrpg = isset($input['show_litrpg_tools']) ? ($input['show_litrpg_tools'] ? 1 : 0) : 1;
    // Convert show_shoutouts to integer for database storage
    $showShoutouts = isset($input['show_shoutouts']) ? ($input['show_shoutouts'] ? 1 : 0) : 0;

    if ($profile) {
        // Update existing profile
        $stmt = $pdo->prepare("
            UPDATE author_profile
            SET name = ?, bio = ?, tagline = ?, profile_image = ?, background_image_light = ?, background_image_dark = ?, site_domain = ?, gallery_rating_filter = ?, show_litrpg_tools = ?, show_shoutouts = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $input['name'] ?? null,
            $input['bio'] ?? null,
            $input['tagline'] ?? null,
            $input['profile_image'] ?? null,
            $input['background_image_light'] ?? null,
            $input['background_image_dark'] ?? null,
            $input['site_domain'] ?? null,
            $input['gallery_rating_filter'] ?? 'auto',
            $showLitrpg,
            $showShoutouts,
            $profile['id']
        ]);
    } else {
        // Create new profile (no created_at column in schema, only updated_at)
        $stmt = $pdo->prepare("
            INSERT INTO author_profile (name, bio, tagline, profile_image, background_image_light, background_image_dark, site_domain, gallery_rating_filter, show_litrpg_tools, show_shoutouts, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $input['name'] ?? null,
            $input['bio'] ?? null,
            $input['tagline'] ?? null,
            $input['profile_image'] ?? null,
            $input['background_image_light'] ?? null,
            $input['background_image_dark'] ?? null,
            $input['site_domain'] ?? null,
            $input['gallery_rating_filter'] ?? 'auto',
            $showLitrpg,
            $showShoutouts
        ]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Author update error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);

    // Return more descriptive error in development
    $errorMsg = 'Failed to update author profile';
    if (defined('DEBUG') && DEBUG) {
        $errorMsg .= ': ' . $e->getMessage();
    }

    echo json_encode([
        'error' => $errorMsg,
        'details' => $e->getMessage(), // Always include details for admin debugging
        'code' => $e->getCode()
    ]);
}
?>
