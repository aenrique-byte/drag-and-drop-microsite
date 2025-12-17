<?php
require_once '../bootstrap.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT key_name, url FROM socials ORDER BY key_name");
    $stmt->execute();
    $socials = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    jsonResponse([
        'success' => true,
        'socials' => $socials
    ]);

} catch (Exception $e) {
    error_log("Socials get error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Failed to fetch social media links'
    ], 500);
}
?>
