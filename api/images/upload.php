<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Check if file was uploaded (support both 'image' and 'file' parameter names)
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
    } else {
        throw new Exception('No file uploaded or upload error');
    }

    // Validate file type
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/webm', 'video/quicktime',
        'audio/mpeg', 'audio/mp3',
        'application/pdf'
    ];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only images (JPEG, PNG, GIF, WebP), videos (MP4, WebM, MOV), audio (MP3), and PDFs are allowed.');
    }

    // Validate file size (max 100MB)
    $maxSize = 100 * 1024 * 1024; // 100MB
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large. Maximum size is 100MB.');
    }
    
    // Determine upload subdirectory based on type
    $type = $_POST['type'] ?? 'general';
    $subDir = '';

    switch ($type) {
        case 'cover':
            $subDir = 'covers/';
            break;
        case 'pagebreak':
            $subDir = 'pagebreaks/';
            break;
        case 'music':
            $subDir = 'music/';
            break;
        default:
            $subDir = 'general/';
            break;
    }
    
    // Create uploads directory structure if it doesn't exist
    $uploadDir = '../uploads/' . $subDir;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Preserve original filename with sanitization and collision handling
    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension === '') {
        // Derive extension from MIME type if original filename lacks an extension
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'application/pdf' => 'pdf',
        ];
        $extension = $mimeToExt[$file['type']] ?? 'bin';
    }
    $base = pathinfo($originalName, PATHINFO_FILENAME);
    // Sanitize base: allow letters, numbers, dashes and underscores; convert spaces to dashes
    $sanitizedBase = preg_replace('/[^A-Za-z0-9_\- ]+/', '-', $base);
    $sanitizedBase = preg_replace('/[ ]+/', '-', $sanitizedBase);
    $sanitizedBase = trim($sanitizedBase, '-_');
    if ($sanitizedBase === '') { $sanitizedBase = 'image'; }
    // Limit base length to prevent overly long filenames
    if (strlen($sanitizedBase) > 100) { $sanitizedBase = substr($sanitizedBase, 0, 100); }
    // Build filename and ensure uniqueness within the target directory
    $filename = $sanitizedBase . '.' . $extension;
    $filepath = $uploadDir . $filename;
    $counter = 1;
    while (file_exists($filepath)) {
        $filename = $sanitizedBase . '_' . $counter . '.' . $extension;
        $filepath = $uploadDir . $filename;
        $counter++;
        if ($counter > 1000) { throw new Exception('Could not generate unique filename'); }
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Return the URL
    $url = '/api/uploads/' . $subDir . $filename;
    
    echo json_encode([
        'success' => true,
        'url' => $url,
        'filename' => $filename
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
