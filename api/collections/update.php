<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Not authenticated']);
  exit;
}

try {
  $pdo = db();
  $input = json_decode(file_get_contents('php://input'), true) ?: [];

  $id = isset($input['id']) ? (int)$input['id'] : 0;
  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Collection ID is required']);
    exit;
  }

  // Ensure exists and get current slug
  $st = $pdo->prepare("SELECT id, slug FROM collections WHERE id = ? LIMIT 1");
  $st->execute([$id]);
  $row = $st->fetch();
  if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Collection not found']);
    exit;
  }

  // Validate slug uniqueness if changing
  if (isset($input['slug'])) {
    $newSlug = trim((string)$input['slug']);
    if ($newSlug === '') {
      http_response_code(400);
      echo json_encode(['error' => 'Slug cannot be empty']);
      exit;
    }
    if ($newSlug !== $row['slug']) {
      $chk = $pdo->prepare("SELECT 1 FROM collections WHERE slug = ? AND id <> ? LIMIT 1");
      $chk->execute([$newSlug, $id]);
      if ($chk->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['error' => 'Slug already exists']);
        exit;
      }
    }
  }

  // Normalize status
  $status = null;
  if (isset($input['status'])) {
    $status = strtolower(trim((string)$input['status']));
    if (!in_array($status, ['draft','published','archived'], true)) {
      $status = null;
    }
  }

  // Normalize themes
  $themesParam = null;
  if (array_key_exists('themes', $input)) {
    $themesRaw = $input['themes'];
    if (is_array($themesRaw)) {
      $san = array_values(array_filter(array_map(function($v) {
        $s = trim((string)$v);
        return $s !== '' ? $s : null;
      }, $themesRaw)));
      $themesParam = !empty($san) ? json_encode($san) : null;
    } else {
      $themesParam = null;
    }
  }

  $fields = [];
  $vals = [];

  foreach (['title','slug','description','cover_hero'] as $f) {
    if (array_key_exists($f, $input)) {
      $fields[] = "$f = ?";
      $vals[] = $input[$f] === '' ? null : $input[$f];
    }
  }

  if ($themesParam !== null || array_key_exists('themes', $input)) {
    $fields[] = "themes = ?";
    $vals[] = $themesParam;
  }

  if ($status !== null) {
    $fields[] = "status = ?";
    $vals[] = $status;
  }

  if (array_key_exists('sort_order', $input)) {
    $fields[] = "sort_order = ?";
    $vals[] = (int)$input['sort_order'];
  }

  if (empty($fields)) {
    http_response_code(400);
    echo json_encode(['error' => 'No fields to update']);
    exit;
  }

  $fields[] = "updated_at = NOW()";
  $sql = "UPDATE collections SET " . implode(', ', $fields) . " WHERE id = ?";
  $vals[] = $id;

  $up = $pdo->prepare($sql);
  $up->execute($vals);

  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  error_log('Collections update error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Failed to update collection']);
}
