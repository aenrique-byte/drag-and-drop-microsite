<?php
declare(strict_types=1);

// Set error handling first
ini_set('display_errors', 0);
error_reporting(E_ALL);

require __DIR__ . '/../bootstrap.php';

// Set JSON content type early
header('Content-Type: application/json');

// PUT/POST /api/images/gallery-update.php
// Body JSON:
// {
//   "id": number (required),
//   "title": string|null (optional),
//   // Optional metadata updates (kept flexible; can be omitted)
//   "prompt": string|null,
//   "parameters": string|null,
//   "checkpoint": string|null,
//   "loras": string[]|null
// }

require_method(['PUT', 'POST']);
requireAuth();

$data = body_json();
$id = isset($data['id']) ? intval($data['id']) : 0;
if ($id <= 0) {
  json_error('id is required', 422);
}

try {

  $titleRaw = array_key_exists('title', $data) ? $data['title'] : null;
  $title = $titleRaw !== null ? (trim((string)$titleRaw) ?: null) : null;

  // Optional metadata fields (pass through if provided)
  $hasPrompt = array_key_exists('prompt', $data);
  $hasParams = array_key_exists('parameters', $data);
  $hasCkpt   = array_key_exists('checkpoint', $data);
  $hasLoras  = array_key_exists('loras', $data);

  $prompt     = $hasPrompt ? (trim((string)$data['prompt']) ?: null) : null;
  $parameters = $hasParams ? (trim((string)$data['parameters']) ?: null) : null;
  $checkpoint = $hasCkpt ? (trim((string)$data['checkpoint']) ?: null) : null;
  $lorasIn    = $hasLoras ? $data['loras'] : null;

  if ($hasLoras && !is_null($lorasIn) && !is_array($lorasIn)) {
    json_error('loras must be null or an array of strings', 422);
  }

  $pdo = db();

  // Ensure image exists
  $st = $pdo->prepare("SELECT id FROM images WHERE id = ?");
  $st->execute([$id]);
  $img = $st->fetch();
  if (!$img) {
    json_error('Image not found', 404);
  }

  $fields = [];
  $params = [];

  if (array_key_exists('title', $data)) { 
    // If title is empty, set it to filename
    if ($titleRaw === null || $titleRaw === '' || trim((string)$titleRaw) === '') {
      // Get the current filename to use as default title
      $filenameStmt = $pdo->prepare("SELECT filename FROM images WHERE id = ?");
      $filenameStmt->execute([$id]);
      $currentImage = $filenameStmt->fetch();
      if ($currentImage) {
        // Use filename without extension as title
        $title = pathinfo($currentImage['filename'], PATHINFO_FILENAME);
      }
    }
    $fields[] = 'title = ?'; 
    $params[] = $title; 
  }
  if ($hasPrompt)   { $fields[] = 'prompt = ?'; $params[] = $prompt; }
  if ($hasParams)   { $fields[] = 'parameters = ?'; $params[] = $parameters; }
  if ($hasCkpt)     { $fields[] = 'checkpoint = ?'; $params[] = $checkpoint; }
  if ($hasLoras)    { $fields[] = 'loras = ?'; $params[] = is_null($lorasIn) ? null : json_encode(array_values($lorasIn)); }

  if (!$fields) {
    // Nothing to update
    $cur = $pdo->prepare("SELECT * FROM images WHERE id = ?");
    $cur->execute([$id]);
    json_response(['image' => $cur->fetch()]);
  }

  $params[] = $id;
  $sql = "UPDATE images SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
  $pdo->prepare($sql)->execute($params);

  $sel = $pdo->prepare("SELECT * FROM images WHERE id = ?");
  $sel->execute([$id]);
  $updated = $sel->fetch();

  json_response(['image' => $updated]);

} catch (Throwable $e) {
  json_error('Failed to update image.', 500, ['detail' => $e->getMessage()]);
}
