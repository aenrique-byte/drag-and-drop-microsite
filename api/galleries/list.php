<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

// Set JSON content type early
header('Content-Type: application/json');

// GET /api/galleries/list.php?page=1&limit=24&search=...
require_method(['GET']);

$page  = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 24)));
$offset = ($page - 1) * $limit;
$search = trim((string)($_GET['search'] ?? ''));

// Optional scoping by collection
$collectionId = isset($_GET['collection_id']) ? (int)$_GET['collection_id'] : null;
$collectionSlug = isset($_GET['collection_slug']) ? trim((string)$_GET['collection_slug']) : null;

try {
  $pdo = db();

  // Visibility & filtering
  $isAuthed = isset($_SESSION['user_id']);
  $includeUnpublished = intval($_GET['include_unpublished'] ?? 0);
  $statusFilter = strtolower(trim((string)($_GET['status'] ?? '')));

  $conditions = [];
  $params = [];
  if ($search !== '') {
    $conditions[] = "(title LIKE ? OR description LIKE ? OR slug LIKE ?)";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
  }

  // Resolve collection by slug if provided
  if ($collectionSlug !== null && $collectionSlug !== '') {
    $stc = $pdo->prepare("SELECT id FROM collections WHERE slug = ? LIMIT 1");
    $stc->execute([$collectionSlug]);
    $cid = $stc->fetchColumn();
    if ($cid) {
      $collectionId = (int)$cid;
    } else {
      // No matching collection -> return empty result fast
      json_response([
        'page' => $page,
        'limit' => $limit,
        'total' => 0,
        'galleries' => []
      ]);
    }
  }

  if ($collectionId !== null) {
    $conditions[] = "collection_id = ?";
    $params[] = $collectionId;
  }

  // By default (public), show only published
  if (!$isAuthed || !$includeUnpublished) {
    $conditions[] = "status = 'published'";
  } else {
    // Admin view can filter by specific status
    if (in_array($statusFilter, ['draft','published','archived'], true)) {
      $conditions[] = "status = ?";
      $params[] = $statusFilter;
    }
    // statusFilter 'all' or empty => no status constraint
  }

  $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

  // Total count
  $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM galleries $where");
  $stmt->execute($params);
  $total = (int)$stmt->fetch()['c'];

  // Page of galleries
  $sql = "SELECT id, slug, collection_id, title, description, status, rating, sort_order, created_by, created_at, updated_at
          FROM galleries
          $where
          ORDER BY sort_order ASC, id DESC
          LIMIT $limit OFFSET $offset";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();

  // Include image counts, likes, and comments
  $ids = array_map(fn($r) => (int)$r['id'], $rows);
  $counts = [];
  $likes = [];
  $comments = [];
  if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    
    // Image counts
    $st2 = $pdo->prepare("SELECT gallery_id, COUNT(*) AS c FROM images WHERE gallery_id IN ($in) GROUP BY gallery_id");
    $st2->execute($ids);
    foreach ($st2->fetchAll() as $r2) {
      $counts[(int)$r2['gallery_id']] = (int)$r2['c'];
    }
    
    // Total likes per gallery
    $st3 = $pdo->prepare("SELECT gallery_id, SUM(like_count) AS total_likes FROM images WHERE gallery_id IN ($in) GROUP BY gallery_id");
    $st3->execute($ids);
    foreach ($st3->fetchAll() as $r3) {
      $likes[(int)$r3['gallery_id']] = (int)$r3['total_likes'];
    }
    
    // Total comments per gallery
    $st4 = $pdo->prepare("SELECT gallery_id, SUM(comment_count) AS total_comments FROM images WHERE gallery_id IN ($in) GROUP BY gallery_id");
    $st4->execute($ids);
    foreach ($st4->fetchAll() as $r4) {
      $comments[(int)$r4['gallery_id']] = (int)$r4['total_comments'];
    }
  }

  // Fetch a representative thumbnail for each gallery (first by sort_order asc, id desc) + its dimensions
  $heroes = [];
  if ($ids) {
    $st3 = $pdo->prepare("SELECT id, thumbnail_path, original_path, width, height FROM images WHERE gallery_id = ? ORDER BY sort_order ASC, id DESC LIMIT 1");
    foreach ($ids as $gid) {
      $st3->execute([$gid]);
      $h = $st3->fetch();
      if ($h && isset($h['thumbnail_path'])) {
        $thumb = (string)$h['thumbnail_path'];
        if ($thumb !== '' && $thumb[0] !== '/') $thumb = '/' . ltrim($thumb, '/');
        $w = isset($h['width']) && $h['width'] !== null ? (int)$h['width'] : null;
        $ht = isset($h['height']) && $h['height'] !== null ? (int)$h['height'] : null;

        // Backfill missing width/height from filesystem (once) to allow ultrawide detection
        if ($w === null || $ht === null) {
          $p = $h['thumbnail_path'] ?? ($h['original_path'] ?? null);
          if (is_string($p) && $p !== '') {
            $rel = $p[0] === '/' ? $p : '/' . ltrim($p, '/');
            $abs = $_SERVER['DOCUMENT_ROOT'] . $rel;
            if (is_file($abs)) {
              $info = @getimagesize($abs);
              if (is_array($info) && isset($info[0], $info[1])) {
                $w = (int)$info[0];
                $ht = (int)$info[1];
                try {
                  $upd = $pdo->prepare("UPDATE images SET width = ?, height = ? WHERE id = ?");
                  $upd->execute([$w, $ht, (int)$h['id']]);
                } catch (Throwable $__) { /* ignore */ }
              }
            }
          }
        }
        $heroes[$gid] = ['thumb' => $thumb, 'width' => $w, 'height' => $ht];
      }
    }
  }

  $galleries = array_map(function($r) use ($counts, $heroes, $likes, $comments) {
    $id = (int)$r['id'];
    $hero = $heroes[$id] ?? null;
    return [
      'id' => $id,
      'slug' => $r['slug'],
      'collection_id' => isset($r['collection_id']) ? (int)$r['collection_id'] : null,
      'title' => $r['title'],
      'description' => $r['description'],
      'status' => $r['status'],
      'rating' => $r['rating'],
      'sort_order' => (int)$r['sort_order'],
      'created_by' => $r['created_by'] !== null ? (int)$r['created_by'] : null,
      'created_at' => $r['created_at'],
      'updated_at' => $r['updated_at'],
      'image_count' => $counts[$id] ?? 0,
      'like_count' => $likes[$id] ?? 0,
      'comment_count' => $comments[$id] ?? 0,
      'hero_thumb' => is_array($hero) ? ($hero['thumb'] ?? null) : (is_string($hero) ? $hero : null),
      'hero_width' => is_array($hero) ? ($hero['width'] ?? null) : null,
      'hero_height' => is_array($hero) ? ($hero['height'] ?? null) : null,
    ];
  }, $rows);

  json_response([
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'galleries' => $galleries,
  ]);
} catch (Throwable $e) {
  json_error('Failed to list galleries.', 500, ['detail' => $e->getMessage()]);
}
