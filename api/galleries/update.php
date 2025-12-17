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
    echo json_encode(['error' => 'Gallery ID is required']);
    exit;
}

try {
    // Check if gallery exists
    $stmt = $pdo->prepare("SELECT id, slug FROM galleries WHERE id = ?");
    $stmt->execute([$input['id']]);
    $gallery = $stmt->fetch();
    if (!$gallery) {
        http_response_code(404);
        echo json_encode(['error' => 'Gallery not found']);
        exit;
    }

    // Check if slug is being changed and if it conflicts
    if (isset($input['slug']) && $input['slug'] !== $gallery['slug']) {
        $stmt = $pdo->prepare("SELECT id FROM galleries WHERE slug = ? AND id != ?");
        $stmt->execute([$input['slug'], $input['id']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Slug already exists']);
            exit;
        }
    }

    // Validate rating if provided
    $rating = null;
    if (isset($input['rating'])) {
        $rating = strtoupper(trim($input['rating']));
        if (!in_array($rating, ['PG', 'X'])) {
            $rating = 'PG'; // Default to PG if invalid
        }
    }

    // Resolve collection assignment if provided (either collection_id or collection_slug)
    $collectionId = null;
    if (array_key_exists('collection_id', $input)) {
        $val = $input['collection_id'];
        if ($val === null || $val === '' ) {
            $collectionId = null;
        } else {
            $collectionId = (int)$val;
            // Verify collection exists
            $stc = $pdo->prepare("SELECT id FROM collections WHERE id = ? LIMIT 1");
            $stc->execute([$collectionId]);
            if (!$stc->fetchColumn()) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid collection_id']);
                exit;
            }
        }
    } elseif (isset($input['collection_slug'])) {
        $slugVal = trim((string)$input['collection_slug']);
        if ($slugVal === '') {
            $collectionId = null;
        } else {
            $stc = $pdo->prepare("SELECT id FROM collections WHERE slug = ? LIMIT 1");
            $stc->execute([$slugVal]);
            $cid = $stc->fetchColumn();
            if (!$cid) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid collection_slug']);
                exit;
            }
            $collectionId = (int)$cid;
        }
    }

    // Validate status if provided
    $status = null;
    if (isset($input['status'])) {
        $status = strtolower(trim($input['status']));
        if (!in_array($status, ['draft','published','archived'], true)) {
            $status = null;
        }
    }

    // Build dynamic UPDATE query based on provided fields
    $updates = [];
    $params = [];

    // Always update updated_at
    $updates[] = "updated_at = NOW()";

    // Only update fields that are explicitly provided
    if (isset($input['title'])) {
        $updates[] = "title = ?";
        $params[] = $input['title'];
    }

    if (isset($input['slug'])) {
        $updates[] = "slug = ?";
        $params[] = $input['slug'];
    }

    if (array_key_exists('description', $input)) {
        $updates[] = "description = ?";
        $params[] = $input['description'];
    }

    if ($rating !== null) {
        $updates[] = "rating = ?";
        $params[] = $rating;
    }

    if ($status !== null) {
        $updates[] = "status = ?";
        $params[] = $status;
    }

    if ($collectionId !== null || array_key_exists('collection_id', $input) || isset($input['collection_slug'])) {
        $updates[] = "collection_id = ?";
        $params[] = $collectionId;
    }

    // Add the WHERE clause parameter
    $params[] = $input['id'];

    $sql = "UPDATE galleries SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Gallery update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update gallery']);
}
?>
