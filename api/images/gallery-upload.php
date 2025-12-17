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

if (!isset($_POST['gallery_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'gallery_id is required']);
    exit;
}

$galleryId = intval($_POST['gallery_id']);
if ($galleryId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'gallery_id must be a positive integer']);
    exit;
}

try {
  $pdo = db();
  // Load gallery and slug
  $gstmt = $pdo->prepare("SELECT id, slug FROM galleries WHERE id = ?");
  $gstmt->execute([$galleryId]);
  $gallery = $gstmt->fetch();
  if (!$gallery) {
    http_response_code(404);
    echo json_encode(['error' => 'Gallery not found']);
    exit;
  }
  $slug = (string)$gallery['slug'];

  if (!isset($_FILES['files'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No files uploaded. Use field name "files[]"']);
    exit;
  }

  $files = $_FILES['files'];
  $titles = isset($_POST['title']) ? (array)$_POST['title'] : [];
  
  // Manual prompt data from form
  $manualPrompts = [
    'positive' => isset($_POST['positive_prompt']) ? trim($_POST['positive_prompt']) : '',
    'negative' => isset($_POST['negative_prompt']) ? trim($_POST['negative_prompt']) : '',
    'checkpoint' => isset($_POST['checkpoint']) ? trim($_POST['checkpoint']) : '',
    'loras' => isset($_POST['loras']) ? trim($_POST['loras']) : ''
  ];
  
  // Check if metadata extraction is enabled
  $extractMetadata = isset($_POST['extract_metadata']) && $_POST['extract_metadata'] === '1';

  // Normalize to arrays
  $names = is_array($files['name']) ? $files['name'] : [$files['name']];
  $types = is_array($files['type']) ? $files['type'] : [$files['type']];
  $tmpns = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
  $errs  = is_array($files['error']) ? $files['error'] : [$files['error']];
  $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];

  $inserted = [];
  $errors = [];
  // Allow up to 100 MB per file (server is already configured accordingly)
  $maxBytes = 100 * 1024 * 1024; // 100 MB per file

  // Allowed mime types
  $imageMime = [
    'image/png'  => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
  ];
  $videoMime = [
    'video/mp4'  => 'mp4',
    'video/webm' => 'webm',
  ];
  $allMime = $imageMime + $videoMime;
  
  // Use separate gallery uploads directory structure
  $galleryUploadsRoot = __DIR__ . '/../uploads/galleries';
  $origDir = $galleryUploadsRoot . '/originals/' . $slug;
  $thumbDir = $galleryUploadsRoot . '/thumbs/' . $slug;
  
  // Ensure directories exist
  @mkdir($origDir, 0775, true);
  @mkdir($thumbDir, 0775, true);

  for ($i = 0; $i < count($names); $i++) {
    $err = (int)($errs[$i] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
      // Skip errored file but keep processing others
      continue;
    }

    $tmpPath = $tmpns[$i];
    $origName = $names[$i];
    $mime = (string)($types[$i] ?? '');
    $size = (int)($sizes[$i] ?? 0);

    // Enforce size and MIME using finfo
    if ($size <= 0 || $size > $maxBytes) {
      $errors[] = ['name' => $origName, 'reason' => 'File too large or empty (max 25MB)'];
      continue;
    }
    $detected = null;
    if (function_exists('finfo_open')) {
      $fi = @finfo_open(FILEINFO_MIME_TYPE);
      if ($fi) {
        $detected = @finfo_file($fi, $tmpPath) ?: null;
        @finfo_close($fi);
      }
    }
    if (!$detected) {
      // Fallback to $_FILES type if finfo unavailable
      $detected = $mime;
    }
    $detected = strtolower(trim((string)$detected));
    if (!isset($allMime[$detected])) {
      $errors[] = ['name' => $origName, 'reason' => 'Unsupported MIME type: ' . $detected];
      continue;
    }
    $ext = $allMime[$detected];

    // If this is a video, handle as a special case (auto-generate poster from first frame using ffmpeg)
    if (strpos($detected, 'video/') === 0) {
      // Ensure unique, safe filename at destination
      $baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
      if ($baseName === '' || $baseName === null) $baseName = 'upload';
      $desiredName = $baseName . '.' . $ext;

      try {
        $dupQ = $pdo->prepare("SELECT id FROM images WHERE gallery_id = ? AND filename = ? LIMIT 1");
        $dupQ->execute([$galleryId, $desiredName]);
        $dupId = $dupQ->fetchColumn();
        if ($dupId) {
          $errors[] = ['name' => $origName, 'reason' => 'Duplicate filename in this gallery (skipped)'];
          continue;
        }
      } catch (Throwable $__) {
        // ignore DB error and proceed to filesystem check
      }
      if (is_file($origDir . DIRECTORY_SEPARATOR . $desiredName)) {
        $errors[] = ['name' => $origName, 'reason' => 'Duplicate filename on disk (skipped)'];
        continue;
      }

      // Generate unique filename if needed
      $counter = 1;
      $destName = $desiredName;
      while (is_file($origDir . DIRECTORY_SEPARATOR . $destName)) {
        $destName = $baseName . '_' . $counter . '.' . $ext;
        $counter++;
      }
      $destOrig = $origDir . DIRECTORY_SEPARATOR . $destName;

      if (!@move_uploaded_file($tmpPath, $destOrig)) {
        if (!@copy($tmpPath, $destOrig)) {
          continue;
        }
      }

      // Build web path for the video
      $origWeb = '/api/uploads/galleries/originals/' . rawurlencode($slug) . '/' . rawurlencode(basename($destOrig));

      // Generate poster from first frame using ffmpeg
      $posterWeb = null;
      $thumbBase = pathinfo($destName, PATHINFO_FILENAME);
      $posterName = $thumbBase . '_poster.jpg';
      $destPoster = $thumbDir . DIRECTORY_SEPARATOR . $posterName;
      @mkdir(dirname($destPoster), 0775, true);

      // Use ffmpeg to extract first frame
      // Check for ffmpeg in custom bin directories or system PATH
      $ffmpegPath = 'ffmpeg'; // default to system PATH
      $possiblePaths = [
        '/home/u473142779/bin/ffmpeg',
        '/home/aenriqu/bin/ffmpeg',
      ];
      foreach ($possiblePaths as $path) {
        if (is_file($path) && is_executable($path)) {
          $ffmpegPath = $path;
          break;
        }
      }

      // Use manual poster upload (ffmpeg not available on shared hosting due to resource limits)
      $posterWeb = null;
      if (isset($_FILES['poster']) && isset($_FILES['poster']['tmp_name'])) {
          $pErr = (int)($_FILES['poster']['error'] ?? UPLOAD_ERR_NO_FILE);
          $pTmp = (string)($_FILES['poster']['tmp_name'] ?? '');
          $pName = (string)($_FILES['poster']['name'] ?? '');
          $pType = (string)($_FILES['poster']['type'] ?? '');
          $pSize = (int)($_FILES['poster']['size'] ?? 0);

          if ($pErr === UPLOAD_ERR_OK && $pTmp && $pSize > 0 && $pSize <= (10 * 1024 * 1024)) {
            $pDetected = null;
            if (function_exists('finfo_open')) {
              $fi2 = @finfo_open(FILEINFO_MIME_TYPE);
              if ($fi2) {
                $pDetected = @finfo_file($fi2, $pTmp) ?: null;
                @finfo_close($fi2);
              }
            }
            if (!$pDetected) $pDetected = $pType;
            $pDetected = strtolower(trim((string)$pDetected));
            if (isset($imageMime[$pDetected])) {
              $pExt = $imageMime[$pDetected];
              $posterName = $thumbBase . '_poster.' . $pExt;
              $destPoster = $thumbDir . DIRECTORY_SEPARATOR . $posterName;
              @mkdir(dirname($destPoster), 0775, true);
              if (@move_uploaded_file($pTmp, $destPoster) || @copy($pTmp, $destPoster)) {
                $posterWeb = '/api/uploads/galleries/thumbs/' . rawurlencode($slug) . '/' . rawurlencode(basename($destPoster));
              }
            }
          }
      }

      // If no poster was generated or supplied, fallback to using original video path as "thumb"
      $thumbWeb = $posterWeb ?: $origWeb;

      // Manual metadata (no extraction for videos)
      $title = isset($titles[$i]) ? trim((string)$titles[$i]) : null;
      if ($title === '') $title = null;

      $finalPrompt = !empty($manualPrompts['positive']) ? $manualPrompts['positive'] : null;
      $finalCheckpoint = !empty($manualPrompts['checkpoint']) ? $manualPrompts['checkpoint'] : null;
      $finalParameters = !empty($manualPrompts['negative']) ? ('Negative prompt: ' . $manualPrompts['negative']) : null;

      $finalLoras = null;
      if (!empty($manualPrompts['loras'])) {
        $lorasArray = array_map('trim', explode(',', $manualPrompts['loras']));
        $lorasArray = array_filter($lorasArray, function($lora) { return !empty($lora); });
        $finalLoras = !empty($lorasArray) ? json_encode(array_values($lorasArray)) : null;
      }

      // Insert DB row for video (set thumbnail_path to poster for grid/hero compatibility)
      $stmt = $pdo->prepare("INSERT INTO images
        (gallery_id, title, filename, original_path, thumbnail_path, media_type, mime_type, poster_path, prompt, parameters, checkpoint, loras, file_size, width, height, sort_order, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
      $sortOrder = 0;

      $stmt->execute([
        $galleryId,
        $title,
        basename($destOrig),
        str_replace('\\', '/', $origWeb),
        str_replace('\\', '/', $thumbWeb),
        'video',
        $detected,
        $posterWeb,
        $finalPrompt,
        $finalParameters,
        $finalCheckpoint,
        $finalLoras,
        filesize($destOrig) ?: $size,
        null, // width (unknown)
        null, // height (unknown)
        $sortOrder,
        $_SESSION['user_id'],
      ]);

      $imgId = (int)$pdo->lastInsertId();

      $inserted[] = [
        'id' => $imgId,
        'title' => $title,
        'src' => str_replace('\\', '/', $origWeb),
        'thumb' => str_replace('\\', '/', $thumbWeb),
        'prompt' => $finalPrompt,
        'parameters' => $finalParameters,
        'checkpoint' => $finalCheckpoint,
        'loras' => $finalLoras ? json_decode($finalLoras, true) : [],
        'file_size' => filesize($destOrig) ?: $size,
        'width' => null,
        'height' => null,
        'sort_order' => $sortOrder,
        'media_type' => 'video',
        'mime_type' => $detected,
      ];

      // Done with this file
      continue;
    }

    // Ensure unique, safe filename at destination
    $baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
    if ($baseName === '' || $baseName === null) $baseName = 'upload';
    $desiredName = $baseName . '.' . $ext;

    // Safeguard: reject duplicate filename within the same gallery
    try {
      $dupQ = $pdo->prepare("SELECT id FROM images WHERE gallery_id = ? AND filename = ? LIMIT 1");
      $dupQ->execute([$galleryId, $desiredName]);
      $dupId = $dupQ->fetchColumn();
      if ($dupId) {
        $errors[] = ['name' => $origName, 'reason' => 'Duplicate filename in this gallery (skipped)'];
        continue;
      }
    } catch (Throwable $__) {
      // ignore DB error and proceed to filesystem check
    }
    if (is_file($origDir . DIRECTORY_SEPARATOR . $desiredName)) {
      $errors[] = ['name' => $origName, 'reason' => 'Duplicate filename on disk (skipped)'];
      continue;
    }

    // Generate unique filename if needed
    $counter = 1;
    $destName = $desiredName;
    while (is_file($origDir . DIRECTORY_SEPARATOR . $destName)) {
      $destName = $baseName . '_' . $counter . '.' . $ext;
      $counter++;
    }
    $destOrig = $origDir . DIRECTORY_SEPARATOR . $destName;

    // Move upload
    if (!@move_uploaded_file($tmpPath, $destOrig)) {
      // Fallback: copy (in some environments)
      if (!@copy($tmpPath, $destOrig)) {
        continue;
      }
    }

    // Validate image and collect dimensions
    $width = null; $height = null;
    $info = @getimagesize($destOrig);
    if (!$info || !is_array($info)) {
      @unlink($destOrig);
      $errors[] = ['name' => $origName, 'reason' => 'Not a valid image'];
      continue;
    }
    $width = isset($info[0]) ? (int)$info[0] : null;
    $height = isset($info[1]) ? (int)$info[1] : null;

    // Create aspect-preserving thumbnail (prefer WebP)
    $thumbBase = pathinfo($destName, PATHINFO_FILENAME);
    $thumbExt = function_exists('imagewebp') ? 'webp' : 'png';
    $destThumb = $thumbDir . DIRECTORY_SEPARATOR . $thumbBase . '.' . $thumbExt;

    @mkdir(dirname($destThumb), 0775, true);
    // Generate an aspect-preserving thumbnail (no crop), max 1280x960
    $thumbOk = create_thumbnail_simple($destOrig, $destThumb, 1280, 960);
    if (!$thumbOk) {
      // If thumbnail fails, still continue but set path to original (not ideal)
      $destThumb = $destOrig;
    }

    // Extract metadata using robust extractor (ComfyUI/A1111) only if enabled
    $meta = ['prompt' => null, 'parameters' => null, 'checkpoint' => null, 'loras' => []];
    if ($extractMetadata) {
      require_once __DIR__ . '/lib/extractor.php';
      $meta = extract_image_metadata($destOrig);
    }

    // Build web paths for gallery images (pointing to api/uploads)
    $origWeb = '/api/uploads/galleries/originals/' . rawurlencode($slug) . '/' . rawurlencode(basename($destOrig));
    // Avoid PHP 8-only str_starts_with for broader compatibility
    $thumbWeb = (strpos($destThumb, $galleryUploadsRoot) === 0)
      ? '/api/uploads/galleries/' . trim(str_replace($galleryUploadsRoot, '', $destThumb), '\\/')
      : $origWeb;
    $thumbWeb = '/' . ltrim(str_replace('\\', '/', $thumbWeb), '/');

    // Title for this file (if provided in array)
    $title = isset($titles[$i]) ? trim((string)$titles[$i]) : null;
    if ($title === '') $title = null;

    // Determine final metadata values - use manual prompts if provided, otherwise fall back to extracted (if enabled)
    $finalPrompt = !empty($manualPrompts['positive']) ? $manualPrompts['positive'] : ($extractMetadata ? ($meta['prompt'] ?? null) : null);
    $finalCheckpoint = !empty($manualPrompts['checkpoint']) ? $manualPrompts['checkpoint'] : ($extractMetadata ? ($meta['checkpoint'] ?? null) : null);
    
    // Handle parameters - combine negative prompt with other parameters if needed
    $finalParameters = null;
    if (!empty($manualPrompts['negative'])) {
      $finalParameters = 'Negative prompt: ' . $manualPrompts['negative'];
      // If there are other extracted parameters and extraction is enabled, append them
      if ($extractMetadata && !empty($meta['parameters']) && stripos($meta['parameters'], 'negative prompt') === false) {
        $finalParameters .= "\n" . $meta['parameters'];
      }
    } else if ($extractMetadata) {
      $finalParameters = $meta['parameters'] ?? null;
    }
    
    // Handle LoRAs - use manual if provided, otherwise extracted (if enabled)
    $finalLoras = null;
    if (!empty($manualPrompts['loras'])) {
      $lorasArray = array_map('trim', explode(',', $manualPrompts['loras']));
      $lorasArray = array_filter($lorasArray, function($lora) { return !empty($lora); });
      $finalLoras = !empty($lorasArray) ? json_encode(array_values($lorasArray)) : null;
    } else if ($extractMetadata && $meta['loras']) {
      $finalLoras = json_encode(array_values($meta['loras']));
    }

    // Insert DB row (image)
    $stmt = $pdo->prepare("INSERT INTO images
      (gallery_id, title, filename, original_path, thumbnail_path, media_type, mime_type, poster_path, prompt, parameters, checkpoint, loras, file_size, width, height, sort_order, uploaded_by)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $sortOrder = 0; // could compute max+1 per gallery if needed

    $stmt->execute([
      $galleryId,
      $title,
      basename($destOrig),
      str_replace('\\', '/', $origWeb),
      str_replace('\\', '/', $thumbWeb),
      'image',
      null,
      null,
      $finalPrompt,
      $finalParameters,
      $finalCheckpoint,
      $finalLoras,
      filesize($destOrig) ?: $size,
      $width,
      $height,
      $sortOrder,
      $_SESSION['user_id'],
    ]);

    $imgId = (int)$pdo->lastInsertId();

    $inserted[] = [
      'id' => $imgId,
      'title' => $title,
      'src' => str_replace('\\', '/', $origWeb),
      'thumb' => str_replace('\\', '/', $thumbWeb),
      'prompt' => $meta['prompt'] ?? null,
      'parameters' => $meta['parameters'] ?? null,
      'checkpoint' => $meta['checkpoint'] ?? null,
      'loras' => $meta['loras'] ?? [],
      'file_size' => filesize($destOrig) ?: $size,
      'width' => $width,
      'height' => $height,
      'sort_order' => $sortOrder,
    ];
  }

  echo json_encode(['success' => true, 'images' => $inserted, 'errors' => $errors]);
} catch (Exception $e) {
  error_log("Image upload error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Upload failed', 'detail' => $e->getMessage()]);
}

// Helper functions (simplified versions for unified system)
function create_thumbnail_simple($src, $dest, $maxW, $maxH) {
  $info = @getimagesize($src);
  if (!$info) return false;
  
  $srcW = $info[0];
  $srcH = $info[1];
  $type = $info[2];
  
  // Calculate new dimensions
  $ratio = min($maxW / $srcW, $maxH / $srcH);
  $newW = (int)($srcW * $ratio);
  $newH = (int)($srcH * $ratio);
  
  // Create source image
  switch ($type) {
    case IMAGETYPE_JPEG:
      $srcImg = @imagecreatefromjpeg($src);
      break;
    case IMAGETYPE_PNG:
      $srcImg = @imagecreatefrompng($src);
      break;
    case IMAGETYPE_WEBP:
      $srcImg = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false;
      break;
    default:
      return false;
  }
  
  if (!$srcImg) return false;
  
  // Create destination image
  $destImg = @imagecreatetruecolor($newW, $newH);
  if (!$destImg) {
    @imagedestroy($srcImg);
    return false;
  }
  
  // Preserve transparency
  @imagealphablending($destImg, false);
  @imagesavealpha($destImg, true);
  
  // Resize
  $success = @imagecopyresampled($destImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
  
  if ($success) {
    // Save thumbnail
    $ext = strtolower(pathinfo($dest, PATHINFO_EXTENSION));
    switch ($ext) {
      case 'webp':
        $success = function_exists('imagewebp') ? @imagewebp($destImg, $dest, 85) : false;
        break;
      case 'png':
        $success = @imagepng($destImg, $dest, 8);
        break;
      default:
        $success = @imagejpeg($destImg, $dest, 85);
    }
  }
  
  @imagedestroy($srcImg);
  @imagedestroy($destImg);
  
  return $success;
}

function extract_image_metadata_simple($imagePath) {
  $meta = [
    'prompt' => null,
    'parameters' => null,
    'checkpoint' => null,
    'loras' => []
  ];
  
  // Try to extract EXIF data for AI-generated images
  if (function_exists('exif_read_data')) {
    $exif = @exif_read_data($imagePath);
    if ($exif) {
      // Common fields where AI metadata is stored
      $fields = ['UserComment', 'ImageDescription', 'Software', 'Artist'];
      foreach ($fields as $field) {
        if (isset($exif[$field]) && is_string($exif[$field])) {
          $data = trim($exif[$field]);
          if (stripos($data, 'prompt') !== false || stripos($data, 'steps') !== false) {
            $meta['parameters'] = $data;
            // Try to extract prompt
            if (preg_match('/prompt[:\s]*([^,\n]+)/i', $data, $matches)) {
              $meta['prompt'] = trim($matches[1]);
            }
            break;
          }
        }
      }
    }
  }
  
  return $meta;
}
