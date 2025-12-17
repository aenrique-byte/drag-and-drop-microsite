<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['title']) || !isset($input['slug'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Title and slug are required']);
    exit;
}

try {
    // Check if slug already exists
    $stmt = $pdo->prepare("SELECT id FROM stories WHERE slug = ?");
    $stmt->execute([$input['slug']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'Slug already exists']);
        exit;
    }

    // Prepare genres as JSON if provided
    $genres_json = null;
    if (isset($input['genres']) && is_array($input['genres'])) {
        $genres_json = json_encode($input['genres']);
    }

    // Prepare external links as JSON if provided: [{label, url}]
    $external_links_json = null;
    if (isset($input['external_links']) && is_array($input['external_links'])) {
        $sanitized = array_values(array_filter(array_map(function($item) {
            if (!is_array($item)) return null;
            $label = isset($item['label']) ? (string)$item['label'] : '';
            $url = isset($item['url']) ? (string)$item['url'] : '';
            if ($label === '' || $url === '') return null;
            return ['label' => $label, 'url' => $url];
        }, $input['external_links'])));
        if (!empty($sanitized)) {
            $external_links_json = json_encode($sanitized);
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO stories (title, slug, description, homepage_description, tagline, genres, external_links, primary_keywords, longtail_keywords, target_audience, cover_image, break_image, enable_drop_cap, drop_cap_font, status, schedule_id, latest_chapter_number, latest_chapter_title, cta_text, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $stmt->execute([
        $input['title'],
        $input['slug'],
        $input['description'] ?? null,
        $input['homepage_description'] ?? null,
        $input['tagline'] ?? null,
        $genres_json,
        $external_links_json,
        $input['primary_keywords'] ?? null,
        $input['longtail_keywords'] ?? null,
        $input['target_audience'] ?? null,
        $input['cover_image'] ?? null,
        $input['break_image'] ?? null,
        isset($input['enable_drop_cap']) ? (int)$input['enable_drop_cap'] : 0,
        $input['drop_cap_font'] ?? 'serif',
        $input['status'] ?? 'draft',
        $input['schedule_id'] ?? null,
        isset($input['latest_chapter_number']) ? (int)$input['latest_chapter_number'] : null,
        $input['latest_chapter_title'] ?? null,
        $input['cta_text'] ?? null
    ]);

    $storyId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'id' => $storyId
    ]);

} catch (Exception $e) {
    error_log("Story create error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create story']);
}
?>
