<?php
// Set error handling to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../bootstrap.php';

header('Content-Type: application/json');

// Check authentication (bootstrap.php already started the session)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // List all uploads organized by type
    try {
        $uploads = [
            'covers' => [],
            'pagebreaks' => [],
            'general' => [],
            'music' => []
        ];

        $baseDir = '../uploads/';

        foreach (['covers', 'pagebreaks', 'general', 'music'] as $type) {
            $typeDir = $baseDir . $type . '/';
            
            if (is_dir($typeDir)) {
                $files = scandir($typeDir);
                
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && $file !== '.htaccess') {
                        $filePath = $typeDir . $file;

                        if (is_file($filePath)) {
                            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                            $allowedExtensions = [
                                'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',  // images
                                'mp4', 'webm', 'mov',  // videos
                                'mp3',  // audio
                                'pdf'  // documents
                            ];

                            if (in_array($extension, $allowedExtensions)) {
                                // Determine MIME type from extension
                                $mimeType = 'application/octet-stream';
                                $extToMime = [
                                    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                                    'png' => 'image/png', 'gif' => 'image/gif',
                                    'webp' => 'image/webp', 'svg' => 'image/svg+xml',
                                    'mp4' => 'video/mp4', 'webm' => 'video/webm',
                                    'mov' => 'video/quicktime', 'mp3' => 'audio/mpeg',
                                    'pdf' => 'application/pdf'
                                ];
                                if (isset($extToMime[$extension])) {
                                    $mimeType = $extToMime[$extension];
                                }

                                $uploads[$type][] = [
                                    'filename' => $file,
                                    'url' => '/api/uploads/' . $type . '/' . $file,
                                    'size' => filesize($filePath),
                                    'modified' => filemtime($filePath),
                                    'type' => $mimeType,
                                    'path' => $type . '/' . $file
                                ];
                            }
                        }
                    }
                }
                
                // Sort by modification time (newest first)
                usort($uploads[$type], function($a, $b) {
                    return $b['modified'] - $a['modified'];
                });
            }
        }
        
        echo json_encode([
            'success' => true,
            'uploads' => $uploads
        ]);
        
    } catch (Exception $e) {
        error_log("Manage uploads list error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to list uploads']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle POST actions (delete, etc.)
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        if (!isset($input['action'])) {
            throw new Exception('Action is required');
        }
        
        if ($input['action'] === 'delete') {
            if (!isset($input['path'])) {
                throw new Exception('File path is required');
            }
            
            $filePath = '../uploads/' . $input['path'];
            
            // Debug logging
            error_log("Delete request for path: " . $input['path']);
            error_log("Full file path: " . $filePath);
            
            // Security check - ensure path is within uploads directory
            $realPath = realpath($filePath);
            $uploadsPath = realpath('../uploads/');
            
            error_log("Real path: " . ($realPath ?: 'false'));
            error_log("Uploads path: " . $uploadsPath);
            
            if (!$realPath || strpos($realPath, $uploadsPath) !== 0) {
                throw new Exception('Invalid file path: ' . $input['path']);
            }
            
            if (!file_exists($filePath)) {
                throw new Exception('File not found: ' . $filePath);
            }
            
            if (!is_writable($filePath)) {
                throw new Exception('File is not writable: ' . $filePath);
            }
            
            if (!unlink($filePath)) {
                throw new Exception('Failed to delete file: ' . $filePath);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);
        } else {
            throw new Exception('Unknown action: ' . $input['action']);
        }
        
    } catch (Exception $e) {
        error_log("Manage uploads delete error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    } catch (Throwable $e) {
        error_log("Manage uploads fatal error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
    }
    
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
