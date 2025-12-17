<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

// Require admin auth
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Not authenticated']);
  exit;
}

try {
  $pdo = db();
  $raw = file_get_contents('php://input');
  $input = json_decode($raw, true) ?: [];

  $title = trim((string)($input['title'] ?? ''));
  $slug  = trim((string)($input['slug'] ?? ''));
  $description = isset($input['description']) ? (string)$input['description'] : null;
  $status = isset($input['status']) ? strtolower(trim((string)$input['status'])) : 'published';
  $coverHero = isset($input['cover_hero']) ? (string)$input['cover_hero'] : null;
  $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;
  $themesRaw = $input['themes'] ?? null;

  if ($title === '' || $slug === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Title and slug are required']);
    exit;
  }

  if (!in_array($status, ['draft','published','archived'], true)) {
    $status = 'published';
  }

  // Enforce unique slug
  $st = $pdo->prepare("SELECT 1 FROM collections WHERE slug = ? LIMIT 1");
  $st->execute([$slug]);
  if ($st->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['error' => 'Slug already exists']);
    exit;
  }

  // Normalize themes as JSON array
  $themesJson = null;
  if (is_array($themesRaw)) {
    $san = array_values(array_filter(array_map(function($v) {
      $s = trim((string)$v);
      return $s !== '' ? $s : null;
    }, $themesRaw)));
    if (!empty($san)) {
      $themesJson = json_encode($san);
    }
  }

  $stmt = $pdo->prepare("
    INSERT INTO collections (slug, title, description, themes, status, cover_hero, sort_order, created_by, created_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
  ");
  $stmt->execute([
    $slug,
    $title,
    $description,
    $themesJson,
    $status,
    $coverHero,
    $sortOrder,
    $_SESSION['user_id'] ?? null
  ]);

  echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
} catch (Throwable $e) {
  error_log('Collections create error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Failed to create collection']);
}
