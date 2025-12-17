<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

// DELETE or POST /api/images/gallery-delete.php
// Body JSON (preferred) or form-encoded:
// { "id": number } or id=123
require_method(['DELETE', 'POST']);
requireRole('editor');

$data = body_json();
$id = 0;

if (isset($data['id'])) {
  $id = intval($data['id']);
} elseif (isset($_POST['id'])) {
  $id = intval($_POST['id']);
} elseif (isset($_GET['id'])) {
  $id = intval($_GET['id']);
}

if ($id <= 0) {
  json_error('id is required', 422);
}

try {
  $pdo = db();

  // Fetch image to get file paths and gallery id
  $st = $pdo->prepare("SELECT id, gallery_id, original_path, thumbnail_path FROM images WHERE id = ?");
  $st->execute([$id]);
  $img = $st->fetch();
  if (!$img) {
    json_error('Image not found', 404);
  }

  $origWeb = (string)($img['original_path'] ?? '');
  $thumbWeb = (string)($img['thumbnail_path'] ?? '');

  // Helper to convert web path to absolute and ensure it's under uploads directory
  $toAbs = function (string $webPath): ?string {
    if ($webPath === '') return null;
    // Normalize slashes
    $p = str_replace('\\', '/', $webPath);
    if ($p[0] !== '/') $p = '/' . $p;
    $abs = $_SERVER['DOCUMENT_ROOT'] . $p;
    // Resolve real path if exists
    $rp = @realpath($abs);
    if ($rp === false) {
      // File might not exist yet; fall back to constructed path
      $rp = $abs;
    }
    // Security: ensure within uploads directory
    $uploads = realpath(UPLOAD_DIR);
    if ($uploads === false) $uploads = UPLOAD_DIR;
    $uploads = rtrim(str_replace('\\', '/', $uploads), '/');
    $rpNorm = str_replace('\\', '/', $rp);
    if (strpos($rpNorm, $uploads) !== 0) {
      return null; // do not allow deletion outside uploads dir
    }
    return $rp;
  };

  $origAbs = $toAbs($origWeb);
  $thumbAbs = $toAbs($thumbWeb);

  // Delete DB row first or after? We'll attempt files first; but keep robust if files missing.
  $errors = [];

  // Delete files (ignore failures but collect messages)
  $deleteFile = function (?string $path) use (&$errors) {
    if (!$path) return;
    if (is_file($path)) {
      if (!@unlink($path)) {
        $errors[] = "Failed to delete file: $path";
      }
    }
  };

  $deleteFile($origAbs);
  $deleteFile($thumbAbs);

  // Remove now-empty gallery subfolders if applicable (best-effort)
  $maybeRmdir = function (?string $filePath) {
    if (!$filePath) return;
    $dir = dirname($filePath);
    // Only attempt to remove if directory exists and is empty
    if (is_dir($dir)) {
      @rmdir($dir); // will only remove if empty
    }
  };
  $maybeRmdir($origAbs);
  $maybeRmdir($thumbAbs);

  // Delete DB row
  $pdo->prepare("DELETE FROM images WHERE id = ?")->execute([$id]);

  $resp = ['ok' => true, 'deleted_id' => (int)$id];
  if ($errors) $resp['warnings'] = $errors;

  json_response($resp);
} catch (Throwable $e) {
  json_error('Failed to delete image.', 500, ['detail' => $e->getMessage()]);
}
