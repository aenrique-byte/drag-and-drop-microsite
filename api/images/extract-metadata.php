<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/lib/extractor.php';

header('Content-Type: application/json');

// POST only
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

// Require editor or admin
requireRole('editor');

$input = body_json();

// Selection modes:
//  - { "id": 123 }                         -> single image
//  - { "gallery_id": 5 }                   -> all images for a gallery
//  - { "limit": 100, "offset": 0 }         -> batch across all images (ordered by id ASC)
// Options:
//  - { "dry_run": true }                   -> do not write to DB, just preview results
//  - { "overwrite": true }                 -> overwrite existing non-null fields
$singleId   = isset($input['id']) ? (int)$input['id'] : null;
$galleryId  = isset($input['gallery_id']) ? (int)$input['gallery_id'] : null;
$limit      = isset($input['limit']) ? max(1, (int)$input['limit']) : null;
$offset     = isset($input['offset']) ? max(0, (int)$input['offset']) : 0;
$dryRun     = !empty($input['dry_run']);
$overwrite  = !empty($input['overwrite']);

try {
  $pdo = db();

  // Build selection query
  $where = [];
  $params = [];

  if ($singleId) {
    $where[] = 'id = ?';
    $params[] = $singleId;
  } elseif ($galleryId) {
    $where[] = 'gallery_id = ?';
    $params[] = $galleryId;
  }

  $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
  $orderSql = ' ORDER BY id ASC ';
  $limitSql = '';

  if (!$singleId && $limit !== null) {
    $limitSql = ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset . ' ';
  }

  $sql = "SELECT id, gallery_id, filename, original_path, thumbnail_path, prompt, parameters, checkpoint, loras
          FROM images
          {$whereSql}
          {$orderSql}
          {$limitSql}";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll();

  // Helper to resolve a web path to filesystem path
  $resolveFsPath = function (?string $webPath): ?string {
    if (!$webPath || !is_string($webPath)) return null;
    $p = trim($webPath);

    // Normalize slashes
    $p = str_replace('\\', '/', $p);

    // We support both "/api/uploads/..." and "/uploads/..."
    if (strpos($p, '/api/uploads/') === 0) {
      $relative = substr($p, strlen('/api/')); // "uploads/..."
      $fs = __DIR__ . '/../' . $relative;      // api/../uploads/...
      return $fs;
    }
    if (strpos($p, '/uploads/') === 0) {
      $relative = ltrim($p, '/');              // "uploads/..."
      $fs = __DIR__ . '/../' . $relative;      // api/../uploads/...
      return $fs;
    }

    // If already absolute on disk, return it if exists
    if (is_file($p)) return $p;

    // Fallback: treat as relative to api/
    $fs = __DIR__ . '/' . ltrim($p, '/');
    return $fs;
  };

  $updated = 0;
  $skipped = 0;
  $errors  = [];
  $results = [];

  foreach ($rows as $r) {
    $id   = (int)$r['id'];
    $srcP = $r['original_path'] ?: $r['thumbnail_path'] ?: null;
    $fs   = $resolveFsPath($srcP);

    if (!$fs || !is_file($fs)) {
      $skipped++;
      $errors[] = ['id' => $id, 'reason' => 'File not found', 'path' => $srcP, 'fs' => $fs];
      continue;
    }

    // Extract
    $meta = extract_image_metadata($fs);
    $newPrompt     = $meta['prompt'] ?? null;
    $newParams     = $meta['parameters'] ?? null;
    $newCheckpoint = $meta['checkpoint'] ?? null;
    $newLorasArr   = is_array($meta['loras'] ?? null) ? array_values(array_unique($meta['loras'])) : [];
    $newLorasJson  = $newLorasArr ? json_encode($newLorasArr) : null;

    // Determine if we should update each field
    $curPrompt     = $r['prompt'] ?? null;
    $curParams     = $r['parameters'] ?? null;
    $curCheckpoint = $r['checkpoint'] ?? null;
    $curLorasJson  = $r['loras'] ?? null;

    $toSet = [];
    $toVals = [];

    if ($overwrite) {
      if ($newPrompt !== null)     { $toSet[] = 'prompt = ?';     $toVals[] = $newPrompt; }
      if ($newParams !== null)     { $toSet[] = 'parameters = ?'; $toVals[] = $newParams; }
      if ($newCheckpoint !== null) { $toSet[] = 'checkpoint = ?'; $toVals[] = $newCheckpoint; }
      if ($newLorasJson !== null)  { $toSet[] = 'loras = ?';      $toVals[] = $newLorasJson; }
    } else {
      if ($newPrompt !== null     && ($curPrompt === null     || $curPrompt === ''))     { $toSet[] = 'prompt = ?';     $toVals[] = $newPrompt; }
      if ($newParams !== null     && ($curParams === null     || $curParams === ''))     { $toSet[] = 'parameters = ?'; $toVals[] = $newParams; }
      if ($newCheckpoint !== null && ($curCheckpoint === null || $curCheckpoint === '')) { $toSet[] = 'checkpoint = ?'; $toVals[] = $newCheckpoint; }
      if ($newLorasJson !== null  && ($curLorasJson === null  || $curLorasJson === ''))  { $toSet[] = 'loras = ?';      $toVals[] = $newLorasJson; }
    }

    $results[] = [
      'id'          => $id,
      'file'        => $fs,
      'extracted'   => [
        'prompt'     => $newPrompt,
        'parameters' => $newParams,
        'checkpoint' => $newCheckpoint,
        'loras'      => $newLorasArr,
      ],
      'will_update' => !$dryRun && !empty($toSet),
    ];

    if (!$dryRun && !empty($toSet)) {
      $toVals[] = $id;
      $q = "UPDATE images SET " . implode(', ', $toSet) . " WHERE id = ?";
      $ust = $pdo->prepare($q);
      try {
        $ust->execute($toVals);
        $updated++;
      } catch (Throwable $ex) {
        $errors[] = ['id' => $id, 'reason' => 'DB update failed', 'detail' => $ex->getMessage()];
      }
    } else {
      $skipped++;
    }
  }

  echo json_encode([
    'success'  => true,
    'count'    => count($rows),
    'updated'  => $dryRun ? 0 : $updated,
    'skipped'  => $dryRun ? count($rows) : $skipped,
    'dry_run'  => $dryRun,
    'overwrite'=> $overwrite,
    'results'  => $results,
    'errors'   => $errors,
  ]);
} catch (Throwable $e) {
  error_log('extract-metadata error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Failed to extract metadata', 'detail' => $e->getMessage()]);
}
