<?php
declare(strict_types=1);
$bootstrapPath = dirname(__DIR__) . '/bootstrap.php';
require_once $bootstrapPath;

// GET /api/shoutouts/config.php - Get shoutout configuration
require_method(['GET']);

try {
    $pdo = db();
    
    // Fetch shoutout configuration
    $stmt = $pdo->query("
        SELECT config_key, config_value 
        FROM shoutout_config
    ");
    
    $rows = $stmt->fetchAll();
    
    // Convert to associative array
    $config = [];
    foreach ($rows as $row) {
        $config[$row['config_key']] = $row['config_value'];
    }
    
    // Convert monthsToShow to integer
    if (isset($config['monthsToShow'])) {
        $config['monthsToShow'] = (int)$config['monthsToShow'];
    }
    
    json_response($config);
    
} catch (Throwable $e) {
    json_error('Failed to fetch shoutout configuration.', 500, ['detail' => $e->getMessage()]);
}
