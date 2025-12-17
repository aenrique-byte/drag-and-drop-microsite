<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// POST /api/email/update.php
require_method(['POST']);
require_auth();

try {
    $pdo = db();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['config'])) {
        json_error('Missing config data', 400);
    }
    
    $config = $input['config'];
    
    // Valid email config keys
    $validKeys = [
        'smtp_host',
        'smtp_port',
        'smtp_user',
        'smtp_pass',
        'smtp_encryption',
        'from_email',
        'from_name',
        'admin_email'
    ];
    
    // Update each config value
    $stmt = $pdo->prepare("
        UPDATE email_config 
        SET config_value = ? 
        WHERE config_key = ?
    ");
    
    foreach ($config as $key => $value) {
        if (!in_array($key, $validKeys)) {
            continue; // Skip invalid keys
        }
        
        $stmt->execute([$value, $key]);
    }
    
    json_response([
        'message' => 'Email configuration updated successfully'
    ]);
    
} catch (Throwable $e) {
    json_error('Failed to update email configuration.', 500, ['detail' => $e->getMessage()]);
}
