<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');
require_method(['GET']);

$page  = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
$offset = ($page - 1) * $limit;

$search = trim((string)($_GET['search'] ?? ''));
$status = isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : null;
$slug   = isset($_GET['slug']) ? trim((string)$_GET['slug']) : null;

try {
  $pdo = db();
  $isAuthed = isset($_SESSION['user_id']);

  $conds = [];
  $params = [];

  if ($search !== '') {
    $like = '%' . $search . '%';
    $conds[] = "(c.title LIKE ? OR c.description LIKE ? OR c.slug LIKE ?)";
    array_push($params, $like, $like, $like);
  }

  if ($slug !== null && $slug !== '') {
    $conds[] = "c.slug = ?";
    $params[] = $slug;
  }

  if (!$isAuthed) {
    $conds[] = "c.status = 'published'";
  } else if (in_array($status ?? '', ['draft','published','archived'], true)) {
    $conds[] = "c.status = ?";
    $params[] = $status;
  }

  $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

  // Total count
  $st = $pdo->prepare("SELECT COUNT(*) FROM collections c $where");
  $st->execute($params);
  $total = (int)$st->fetchColumn();

  // Page of collections with gallery_count
  $sql = "
    SELECT c.*,
      (SELECT COUNT(*) FROM galleries g WHERE g.collection_id = c.id) AS gallery_count
    FROM collections c
    $where
    ORDER BY c.sort_order ASC, c.id DESC
    LIMIT ? OFFSET ?
  ";
  $finalParams = array_merge($params, [$limit, $offset]);
  $st2 = $pdo->prepare($sql);
  $st2->execute($finalParams);
  $rows = $st2->fetchAll();

  // Normalize JSON fields
  foreach ($rows as &$r) {
    if (!empty($r['themes'])) {
      $dec = json_decode($r['themes'], true);
      $r['themes'] = is_array($dec) ? $dec : [];
    } else {
      $r['themes'] = [];
    }
    $r['gallery_count'] = (int)($r['gallery_count'] ?? 0);
  }

  json_response([
    'success' => true,
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'collections' => $rows
  ]);
} catch (Throwable $e) {
  json_error('Failed to list collections.', 500, ['detail' => $e->getMessage()]);
}
