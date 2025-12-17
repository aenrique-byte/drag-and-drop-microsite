<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

require_method(['GET']);

$galleryId = intval($_GET['gallery_id'] ?? 0);
if ($galleryId <= 0) {
    json_error('gallery_id is required', 400);
}

$page  = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 24)));
$offset = ($page - 1) * $limit;
$q = trim((string)($_GET['q'] ?? ''));

try {
  $pdo = db();
  
  // Ensure gallery exists and get slug/status (for clients that may need paths)
  $gstmt = $pdo->prepare("SELECT id, slug, title, status FROM galleries WHERE id = ?");
  $gstmt->execute([$galleryId]);
  $gallery = $gstmt->fetch();
  if (!$gallery) {
    json_error('Gallery not found', 404);
  }
  // Block access to non-published galleries for non-authenticated users
  $isAuthed = isset($_SESSION['user_id']);
  if (!$isAuthed && isset($gallery['status']) && $gallery['status'] !== 'published') {
    json_error('Gallery not found', 404);
  }
  $slug = $gallery['slug'];

  $where = "WHERE gallery_id = ?";
  $params = [$galleryId];

  if ($q !== '') {
    $where .= " AND (title LIKE ? OR prompt LIKE ? OR parameters LIKE ? OR checkpoint LIKE ? OR JSON_SEARCH(loras, 'one', ?, NULL, '$') IS NOT NULL)";
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $q);
  }

  // Total
  $stc = $pdo->prepare("SELECT COUNT(*) AS c FROM images $where");
  $stc->execute($params);
  $total = (int)$stc->fetch()['c'];

  // Page (alphabetical by Title if present, else by filename)
  $sql = "SELECT id, title, filename, original_path, thumbnail_path, media_type, mime_type, poster_path, prompt, parameters, checkpoint, loras, file_size, width, height, sort_order, uploaded_by, created_at, updated_at
          FROM images
          $where
          ORDER BY
            CASE
              WHEN title IS NOT NULL AND title != '' THEN title
              ELSE filename
            END ASC,
            id DESC
          LIMIT $limit OFFSET $offset";
  $sti = $pdo->prepare($sql);
  $sti->execute($params);
  $rows = $sti->fetchAll();

  // Backfill missing width/height from filesystem (once), so UI can detect ultrawide
  foreach ($rows as &$r) {
    $w = $r['width'] ?? null;
    $h = $r['height'] ?? null;
    if ($w === null || $h === null) {
      $p = $r['thumbnail_path'] ?? ($r['original_path'] ?? null);
      if (is_string($p) && $p !== '') {
        $rel = $p[0] === '/' ? $p : '/' . ltrim($p, '/');
        $abs = $_SERVER['DOCUMENT_ROOT'] . $rel;
        if (is_file($abs)) {
          $info = @getimagesize($abs);
          if (is_array($info) && isset($info[0], $info[1])) {
            $r['width'] = (int)$info[0];
            $r['height'] = (int)$info[1];
            try {
              $upd = $pdo->prepare("UPDATE images SET width = ?, height = ? WHERE id = ?");
              $upd->execute([(int)$info[0], (int)$info[1], (int)$r['id']]);
            } catch (Throwable $__) { /* ignore */ }
          }
        }
      }
    }
  }
  unset($r);

  // Normalize and ensure paths are web-accessible (fix paths to point to /api/uploads/)
  $images = array_map(function($r) {
    $loras = null;
    if (isset($r['loras'])) {
      if (is_string($r['loras'])) {
        $decoded = json_decode($r['loras'], true);
        $loras = is_array($decoded) ? array_values($decoded) : null;
      } elseif (is_array($r['loras'])) {
        $loras = array_values($r['loras']);
      }
    }
    
    $orig = $r['original_path'];
    $thumb = $r['thumbnail_path'];
    $poster = $r['poster_path'] ?? null;
    
    // Fix paths to point to /api/uploads/ instead of /uploads/
    if ($orig !== null && $orig !== '') {
      if (strpos($orig, '/uploads/') === 0) {
        $orig = '/api' . $orig;
      } elseif ($orig[0] !== '/') {
        $orig = '/' . ltrim($orig, '/');
      }
    }
    
    if ($thumb !== null && $thumb !== '') {
      if (strpos($thumb, '/uploads/') === 0) {
        $thumb = '/api' . $thumb;
      } elseif ($thumb[0] !== '/') {
        $thumb = '/' . ltrim($thumb, '/');
      }
    }
    if ($poster !== null && $poster !== '') {
      if (strpos($poster, '/uploads/') === 0) {
        $poster = '/api' . $poster;
      } elseif ($poster[0] !== '/') {
        $poster = '/' . ltrim($poster, '/');
      }
    }

    // Sanitize prompt/parameters if the prompt field contains serialized workflow JSON
    $p = $r['prompt'] ?? null;
    $n = $r['parameters'] ?? null;
    // Simple sanitization for unified system
    if ($p && strlen($p) > 1000) {
      $p = substr($p, 0, 1000) . '...';
    }
    if ($n && strlen($n) > 2000) {
      $n = substr($n, 0, 2000) . '...';
    }

    return [
      'id' => (int)$r['id'],
      'title' => $r['title'],
      'src' => $orig,
      'thumb' => $thumb,
      'media_type' => isset($r['media_type']) && $r['media_type'] ? $r['media_type'] : 'image',
      'mime_type' => $r['mime_type'] ?? null,
      'poster' => $poster,
      'prompt' => $p,
      'parameters' => $n,
      'checkpoint' => $r['checkpoint'],
      'loras' => $loras ?? [],
      'file_size' => $r['file_size'] !== null ? (int)$r['file_size'] : null,
      'width' => $r['width'] !== null ? (int)$r['width'] : null,
      'height' => $r['height'] !== null ? (int)$r['height'] : null,
      'sort_order' => (int)$r['sort_order'],
      'uploaded_by' => $r['uploaded_by'] !== null ? (int)$r['uploaded_by'] : null,
      'created_at' => $r['created_at'],
      'updated_at' => $r['updated_at'],
    ];
  }, $rows);

  json_response([
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'gallery' => [
      'id' => (int)$gallery['id'],
      'slug' => $slug,
      'title' => $gallery['title'],
      'status' => $gallery['status'] ?? null,
    ],
    'images' => $images,
  ]);
} catch (Throwable $e) {
  json_error('Failed to list images', 500, ['detail' => $e->getMessage()]);
}
