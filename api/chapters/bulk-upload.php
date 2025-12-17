<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check authentication (session already started in bootstrap.php)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Check if files were uploaded
if (!isset($_FILES['markdown_files']) || !is_array($_FILES['markdown_files']['name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No markdown files uploaded']);
    exit;
}

// Get story_id from POST data
$story_id = $_POST['story_id'] ?? null;
if (!$story_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Story ID is required']);
    exit;
}

try {
    // Verify story exists
    $stmt = $pdo->prepare("SELECT id FROM stories WHERE id = ?");
    $stmt->execute([$story_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Story not found']);
        exit;
    }

    // Get the highest chapter number for this story
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(chapter_number), 0) as max_chapter FROM chapters WHERE story_id = ?");
    $stmt->execute([$story_id]);
    $result = $stmt->fetch();
    $next_chapter_number = $result['max_chapter'] + 1;

    $uploaded_chapters = [];
    $errors = [];

    // Process each uploaded file
    $file_count = count($_FILES['markdown_files']['name']);
    
    for ($i = 0; $i < $file_count; $i++) {
        $file_name = $_FILES['markdown_files']['name'][$i];
        $file_tmp = $_FILES['markdown_files']['tmp_name'][$i];
        $file_error = $_FILES['markdown_files']['error'][$i];
        
        // Skip if there was an upload error
        if ($file_error !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading file: $file_name";
            continue;
        }
        
        // Read file content
        $content = file_get_contents($file_tmp);
        if ($content === false) {
            $errors[] = "Could not read file: $file_name";
            continue;
        }
        
        // Extract chapter title from content
        $chapter_title = extractChapterTitle($content, $file_name);
        $chapter_slug = generateSlug($chapter_title);
        
        // Check if slug already exists for this story
        $stmt = $pdo->prepare("SELECT id FROM chapters WHERE story_id = ? AND slug = ?");
        $stmt->execute([$story_id, $chapter_slug]);
        if ($stmt->fetch()) {
            // If slug exists, append a number
            $counter = 1;
            $original_slug = $chapter_slug;
            do {
                $chapter_slug = $original_slug . '-' . $counter;
                $stmt = $pdo->prepare("SELECT id FROM chapters WHERE story_id = ? AND slug = ?");
                $stmt->execute([$story_id, $chapter_slug]);
                $counter++;
            } while ($stmt->fetch());
        }
        
        // Check if chapter number already exists for this story
        $stmt = $pdo->prepare("SELECT id FROM chapters WHERE story_id = ? AND chapter_number = ?");
        $stmt->execute([$story_id, $next_chapter_number]);
        if ($stmt->fetch()) {
            // Find the next available chapter number
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(chapter_number), 0) + 1 as next_num FROM chapters WHERE story_id = ?");
            $stmt->execute([$story_id]);
            $result = $stmt->fetch();
            $next_chapter_number = $result['next_num'];
        }
        
        // Insert the chapter
        $stmt = $pdo->prepare("
            INSERT INTO chapters (story_id, title, slug, content, chapter_number, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'draft', NOW(), NOW())
        ");
        
        $stmt->execute([
            $story_id,
            $chapter_title,
            $chapter_slug,
            $content,
            $next_chapter_number
        ]);
        
        $chapter_id = $pdo->lastInsertId();
        
        $uploaded_chapters[] = [
            'id' => $chapter_id,
            'title' => $chapter_title,
            'slug' => $chapter_slug,
            'chapter_number' => $next_chapter_number,
            'filename' => $file_name
        ];
        
        $next_chapter_number++;
    }
    
    // Update story's updated_at timestamp
    $stmt = $pdo->prepare("UPDATE stories SET updated_at = NOW() WHERE id = ?");
    $stmt->execute([$story_id]);
    
    $response = [
        'success' => true,
        'uploaded_count' => count($uploaded_chapters),
        'chapters' => $uploaded_chapters
    ];
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Bulk chapter upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to upload chapters: ' . $e->getMessage()]);
}

/**
 * Extract chapter title from markdown content
 */
function extractChapterTitle($content, $filename) {
    // Look for markdown headers that match chapter patterns
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Look for # Chapter patterns
        if (preg_match('/^#\s+Chapter\s+(\d+)(?:\s*[-–—]\s*(.+))?/i', $line, $matches)) {
            if (isset($matches[2]) && !empty(trim($matches[2]))) {
                return "Chapter " . $matches[1] . " - " . trim($matches[2]);
            } else {
                return "Chapter " . $matches[1];
            }
        }
        
        // Look for any # header as fallback
        if (preg_match('/^#\s+(.+)/', $line, $matches)) {
            return trim($matches[1]);
        }
    }
    
    // If no header found, use filename without extension
    $title = pathinfo($filename, PATHINFO_FILENAME);
    
    // Try to extract chapter info from filename
    if (preg_match('/chapter[\s_-]*(\d+)/i', $title, $matches)) {
        return "Chapter " . $matches[1];
    }
    
    return $title;
}

/**
 * Generate URL-friendly slug from title
 */
function generateSlug($title) {
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    
    return $slug;
}
?>
