<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Check authentication - session already started in bootstrap.php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    // Get all uploaded images that could be used as page breaks
    // Look for images in uploads/pagebreaks directory
    $uploadsDir = '../uploads/pagebreaks/';
    $images = [];
    
    if (is_dir($uploadsDir)) {
        $files = scandir($uploadsDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && $file !== '.htaccess') {
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                    $images[] = [
                        'filename' => $file,
                        'url' => '/api/uploads/pagebreaks/' . $file,
                        'size' => filesize($uploadsDir . $file),
                        'modified' => filemtime($uploadsDir . $file)
                    ];
                }
            }
        }
    }
    
    // Sort by modification time (newest first)
    usort($images, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });

    echo json_encode([
        'success' => true,
        'images' => $images
    ]);

} catch (Exception $e) {
    error_log("List pagebreaks error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to list page break images']);
}
?>
