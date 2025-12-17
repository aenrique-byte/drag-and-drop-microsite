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

if (!$input || !isset($input['title'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Title is required']);
    exit;
}

// Helper function for slug generation
function slugify($text) {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

try {
    $pdo = db();

    $title = trim($input['title']);
    $description = isset($input['description']) ? trim($input['description']) : null;
    $rating = strtoupper(trim($input['rating'] ?? 'PG'));
    $customSlug = isset($input['slug']) ? trim($input['slug']) : null;

    // Optional: assign to a collection (by id or slug)
    $collectionId = null;
    if (array_key_exists('collection_id', $input)) {
        $val = $input['collection_id'];
        if ($val !== null && $val !== '') {
            $collectionId = (int)$val;
            // Validate collection exists
            $stc = $pdo->prepare("SELECT id FROM collections WHERE id = ? LIMIT 1");
            $stc->execute([$collectionId]);
            if (!$stc->fetchColumn()) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid collection_id']);
                exit;
            }
        }
    } elseif (isset($input['collection_slug'])) {
        $cslug = trim((string)$input['collection_slug']);
        if ($cslug !== '') {
            $stc = $pdo->prepare("SELECT id FROM collections WHERE slug = ? LIMIT 1");
            $stc->execute([$cslug]);
            $cid = $stc->fetchColumn();
            if (!$cid) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid collection_slug']);
                exit;
            }
            $collectionId = (int)$cid;
        }
    }

    // Publication status: default to draft for new galleries
    $status = strtolower(trim($input['status'] ?? 'draft'));
    if (!in_array($status, ['draft','published','archived'], true)) {
        $status = 'draft';
    }

    if (!in_array($rating, ['PG','X'], true)) {
        $rating = 'PG';
    }

    // Build unique slug
    $base = $customSlug !== null && $customSlug !== '' ? $customSlug : slugify($title);
    if ($base === '') $base = 'gallery';

    $slug = $base;
    $i = 1;
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM galleries WHERE slug = ?");
    while (true) {
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        $exists = $row ? ((int)$row['c'] > 0) : false;
        if (!$exists) break;
        $slug = $base . '-' . $i;
        $i++;
    }

    // sort_order = max + 1
    $maxStmt = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) AS m FROM galleries");
    $maxRow = $maxStmt->fetch();
    $sortOrder = (int)($maxRow ? $maxRow['m'] : 0) + 1;

    $ins = $pdo->prepare("INSERT INTO galleries (slug, title, description, status, rating, sort_order, created_by, collection_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->execute([$slug, $title, $description !== '' ? $description : null, $status, $rating, $sortOrder, $_SESSION['user_id'], $collectionId]);

    $id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'gallery' => [
            'id' => $id,
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'rating' => $rating,
            'status' => $status,
            'sort_order' => $sortOrder,
            'created_by' => $_SESSION['user_id'],
            'collection_id' => $collectionId,
        ]
    ]);

} catch (Exception $e) {
    error_log("Gallery create error: " . $e->getMessage());
    $msg = strtolower($e->getMessage());
    if ((strpos($msg, 'duplicate') !== false) || (strpos($msg, 'unique') !== false)) {
        http_response_code(409);
        echo json_encode(['error' => 'A gallery with that slug already exists', 'detail' => $e->getMessage()]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create gallery', 'detail' => $e->getMessage()]);
    }
}
?>
