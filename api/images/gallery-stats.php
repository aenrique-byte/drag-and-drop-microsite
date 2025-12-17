<?php
declare(strict_types=1);
require __DIR__ . '/../bootstrap.php';

// GET /api/images/gallery-stats.php?image_id=123
// Returns counters and whether current IP has liked the image.
// Response:
// { ok: true, likes: number, comments: number, liked: boolean }
require_method(['GET']);

$imageId = intval($_GET['image_id'] ?? 0);
if ($imageId <= 0) {
  json_error('image_id is required', 422);
}

try {
  $pdo = db();

  // Ensure image exists
  $s = $pdo->prepare("SELECT id, like_count, comment_count FROM images WHERE id = ?");
  $s->execute([$imageId]);
  $img = $s->fetch();
  if (!$img) {
    json_error('Image not found', 404);
  }

  // Has current IP liked?
  $ip = client_ip();
  $liked = false;
  try {
    $q = $pdo->prepare("SELECT 1 FROM image_likes WHERE image_id = ? AND ip_address = ? LIMIT 1");
    $q->execute([$imageId, $ip]);
    $liked = (bool)$q->fetchColumn();
  } catch (Throwable $__) {
    $liked = false;
  }

  json_response([
    'ok' => true,
    'likes' => (int)($img['like_count'] ?? 0),
    'comments' => (int)($img['comment_count'] ?? 0),
    'liked' => $liked,
  ]);
} catch (Throwable $e) {
  json_error('Failed to fetch stats', 500, ['detail' => $e->getMessage()]);
}

// Helper function for unified system
function client_ip() {
  // Get client IP address
  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    return $_SERVER['HTTP_CLIENT_IP'];
  } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    return $_SERVER['HTTP_X_FORWARDED_FOR'];
  } else {
    return $_SERVER['REMOTE_ADDR'];
  }
}
