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

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Clear existing social links
    $stmt = $pdo->prepare("DELETE FROM socials");
    $stmt->execute();

    // Insert new social links (match schema: key_name, url)
    // Use UPSERT to avoid duplicates when migrating from older data
    $stmt = $pdo->prepare("INSERT INTO socials (key_name, url) VALUES (?, ?) ON DUPLICATE KEY UPDATE url = VALUES(url)");
    
    foreach ($input as $platform => $url) {
        if (!empty(trim($url))) {
            $stmt->execute([$platform, trim($url)]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Socials update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update social links']);
}
?>
