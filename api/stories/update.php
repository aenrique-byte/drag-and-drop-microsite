<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check authentication - session is already started in bootstrap.php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Story ID is required']);
    exit;
}

try {
    // Check if story exists and get current values
    $stmt = $pdo->prepare("SELECT id, slug FROM stories WHERE id = ?");
    $stmt->execute([$input['id']]);
    $story = $stmt->fetch();
    if (!$story) {
        http_response_code(404);
        echo json_encode(['error' => 'Story not found']);
        exit;
    }

    // Check if slug is being changed and if it conflicts
    if (isset($input['slug']) && $input['slug'] !== $story['slug']) {
        $stmt = $pdo->prepare("SELECT id FROM stories WHERE slug = ? AND id != ?");
        $stmt->execute([$input['slug'], $input['id']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Slug already exists']);
            exit;
        }
    }

    // Build dynamic UPDATE query based on provided fields
    $updateFields = [];
    $updateValues = [];

    // Handle genres specially (needs JSON encoding)
    if (isset($input['genres'])) {
        if (is_array($input['genres'])) {
            $updateFields[] = "genres = ?";
            $updateValues[] = json_encode($input['genres']);
        } else {
            $updateFields[] = "genres = ?";
            $updateValues[] = $input['genres'];
        }
    }

    // Handle external_links specially (array of {label,url} -> JSON)
    if (isset($input['external_links'])) {
        if (is_array($input['external_links'])) {
            $sanitized = array_values(array_filter(array_map(function($item) {
                if (!is_array($item)) return null;
                $label = isset($item['label']) ? (string)$item['label'] : '';
                $url = isset($item['url']) ? (string)$item['url'] : '';
                if ($label === '' || $url === '') return null;
                return ['label' => $label, 'url' => $url];
            }, $input['external_links'])));
            $updateFields[] = "external_links = ?";
            $updateValues[] = !empty($sanitized) ? json_encode($sanitized) : null;
        } else {
            // Allow raw JSON string pass-through (admin tools)
            $updateFields[] = "external_links = ?";
            $updateValues[] = $input['external_links'];
        }
    }

    // Handle enable_drop_cap as boolean/int
    if (isset($input['enable_drop_cap'])) {
        $updateFields[] = "enable_drop_cap = ?";
        $updateValues[] = (int)$input['enable_drop_cap'];
    }

    // Handle show_on_homepage as boolean/int
    if (isset($input['show_on_homepage'])) {
        $updateFields[] = "show_on_homepage = ?";
        $updateValues[] = (int)$input['show_on_homepage'];
    }

    // Handle latest_chapter_number as int (allow null)
    if (isset($input['latest_chapter_number'])) {
        $updateFields[] = "latest_chapter_number = ?";
        $updateValues[] = $input['latest_chapter_number'] !== '' && $input['latest_chapter_number'] !== null
            ? (int)$input['latest_chapter_number']
            : null;
    }

    // Simple fields that can be directly updated
    $simpleFields = ['title', 'slug', 'description', 'homepage_description', 'tagline', 'primary_keywords', 'longtail_keywords',
                     'target_audience', 'cover_image', 'break_image', 'drop_cap_font', 'status',
                     'latest_chapter_title', 'cta_text'];

    foreach ($simpleFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = ?";
            $updateValues[] = $input[$field];
        }
    }

    if (empty($updateFields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }

    // Always update the timestamp
    $updateFields[] = "updated_at = NOW()";

    $sql = "UPDATE stories SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $updateValues[] = $input['id'];

    $stmt = $pdo->prepare($sql);
    $stmt->execute($updateValues);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Story update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update story']);
}
?>
