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

if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Gallery ID is required']);
    exit;
}

$id = intval($input['id']);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid gallery ID']);
    exit;
}

try {
  $pdo = db();
  
  // Fetch gallery to know slug for file cleanup
  $st = $pdo->prepare("SELECT id, slug FROM galleries WHERE id = ?");
  $st->execute([$id]);
  $g = $st->fetch();
  if (!$g) {
    http_response_code(404);
    echo json_encode(['error' => 'Gallery not found']);
    exit;
  }
  $slug = (string)$g['slug'];

  // Recursive directory delete helper
  $rrmdir = function (string $dir) use (&$rrmdir) {
    if (!is_dir($dir)) return;
    $items = @scandir($dir);
    if ($items === false) return;
    foreach ($items as $item) {
      if ($item === '.' || $item === '..') continue;
      $path = $dir . DIRECTORY_SEPARATOR . $item;
      if (is_dir($path)) {
        $rrmdir($path);
      } else {
        @unlink($path);
      }
    }
    @rmdir($dir);
  };

  // Delete DB gallery (will cascade to images table)
  $pdo->prepare("DELETE FROM galleries WHERE id = ?")->execute([$id]);

  // Remove gallery uploads directories (best-effort)
  $galleryUploadsRoot = __DIR__ . '/../uploads/galleries';
  $origDir = $galleryUploadsRoot . '/originals/' . $slug;
  $thumbDir = $galleryUploadsRoot . '/thumbs/' . $slug;
  $rrmdir($origDir);
  $rrmdir($thumbDir);

  echo json_encode(['success' => true, 'deleted_id' => (int)$id]);
} catch (Exception $e) {
  error_log("Gallery delete error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Failed to delete gallery']);
}
