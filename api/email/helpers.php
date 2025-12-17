<?php
/**
 * Email Configuration Helpers
 * 
 * Provides centralized email/SMTP functionality for all features
 * (shoutouts, blog, notifications, etc.)
 */

/**
 * Get email configuration from database
 * 
 * @return array Associative array of email config
 */
function get_email_config(): array {
    static $cache = null;
    
    if ($cache !== null) {
        return $cache;
    }
    
    try {
        $pdo = db();
        $stmt = $pdo->query("SELECT config_key, config_value FROM email_config");
        $rows = $stmt->fetchAll();
        
        $config = [];
        foreach ($rows as $row) {
            $config[$row['config_key']] = $row['config_value'];
        }
        
        $cache = $config;
        return $config;
        
    } catch (Throwable $e) {
        error_log("Failed to fetch email config: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a specific email configuration value
 * 
 * @param string $key Configuration key
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value or default
 */
function get_email_config_value(string $key, $default = null) {
    $config = get_email_config();
    return $config[$key] ?? $default;
}

/**
 * Check if email configuration is complete
 * 
 * @return bool True if SMTP settings are configured
 */
function is_email_configured(): bool {
    $config = get_email_config();
    
    $required = ['smtp_host', 'smtp_user', 'smtp_pass', 'from_email'];
    
    foreach ($required as $key) {
        if (empty($config[$key])) {
            return false;
        }
    }
    
    return true;
}

/**
 * Send email using configured SMTP settings
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param array $headers Additional headers
 * @return bool Success status
 */
function send_smtp_email(string $to, string $subject, string $body, array $headers = []): bool {
    if (!is_email_configured()) {
        error_log("Email not configured - cannot send email");
        return false;
    }
    
    $config = get_email_config();
    
    // Build headers
    $from = $config['from_email'];
    $fromName = $config['from_name'] ?? 'Website';
    
    $defaultHeaders = [
        "From: {$fromName} <{$from}>",
        "Reply-To: {$from}",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8"
    ];
    
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    // Use PHP's mail() function (requires server SMTP configuration)
    // For more advanced SMTP, consider PHPMailer library
    return mail($to, $subject, $body, implode("\r\n", $allHeaders));
}
