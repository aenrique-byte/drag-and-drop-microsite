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
  $force = isset($input['force']) ? (bool)$input['force'] : false;

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Collection ID is required']);
    exit;
  }

  // Verify collection exists
  $st = $pdo->prepare("SELECT id FROM collections WHERE id = ? LIMIT 1");
  $st->execute([$id]);
  if (!$st->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['error' => 'Collection not found']);
    exit;
  }

  // Check if any galleries are assigned
  $sg = $pdo->prepare("SELECT COUNT(*) FROM galleries WHERE collection_id = ?");
  $sg->execute([$id]);
  $assigned = (int)$sg->fetchColumn();

  if ($assigned > 0 && !$force) {
    http_response_code(400);
    echo json_encode(['error' => 'Collection has assigned galleries. Use force=true to delete and clear assignments.']);
    exit;
  }

  $pdo->beginTransaction();
  try {
    if ($assigned > 0) {
      // Clear assignments
      $clr = $pdo->prepare("UPDATE galleries SET collection_id = NULL WHERE collection_id = ?");
      $clr->execute([$id]);
    }
    // Delete collection
    $del = $pdo->prepare("DELETE FROM collections WHERE id = ?");
    $del->execute([$id]);

    $pdo->commit();
  } catch (Throwable $tx) {
    $pdo->rollBack();
    throw $tx;
  }

  echo json_encode(['success' => true, 'cleared_galleries' => $assigned]);
} catch (Throwable $e) {
  error_log('Collections delete error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Failed to delete collection']);
}
