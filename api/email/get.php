<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// GET /api/email/get.php
require_method(['GET']);

try {
    $pdo = db();
    
    // Fetch all email configuration
    $stmt = $pdo->query("
        SELECT config_key, config_value, description 
        FROM email_config 
        ORDER BY id ASC
    ");
    
    $rows = $stmt->fetchAll();
    
    // Convert to associative array for easier frontend use
    $config = [];
    $descriptions = [];
    
    foreach ($rows as $row) {
        $config[$row['config_key']] = $row['config_value'];
        if ($row['description']) {
            $descriptions[$row['config_key']] = $row['description'];
        }
    }
    
    json_response([
        'config' => $config,
        'descriptions' => $descriptions
    ]);
    
} catch (Throwable $e) {
    json_error('Failed to fetch email configuration.', 500, ['detail' => $e->getMessage()]);
}
