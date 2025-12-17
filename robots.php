<?php
require_once '../api/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

// Dynamic base URL from database or server
function getBaseUrl($pdo) {
    try {
        // Try to get domain from site_config table
        $stmt = $pdo->prepare("SELECT config_value FROM site_config WHERE config_key = 'site_domain' LIMIT 1");
        $stmt->execute();
        $domain = $stmt->fetchColumn();
        
        if ($domain) {
            return 'https://' . $domain;
        }
    } catch (Exception $e) {
        // Fallback if site_config doesn't exist yet
    }
    
    // Try to get from author_profile
    try {
        $stmt = $pdo->prepare("SELECT site_domain FROM author_profile LIMIT 1");
        $stmt->execute();
        $domain = $stmt->fetchColumn();
        
        if ($domain) {
            return 'https://' . $domain;
        }
    } catch (Exception $e) {
        // Fallback if author_profile doesn't exist
    }
    
    // Final fallback to server detection
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'example.com';
    return $protocol . '://' . $host;
}

$baseUrl = getBaseUrl($pdo);

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /imagemanager/\n";
echo "\n";
echo "Sitemap: {$baseUrl}/api/sitemap.xml\n";
?>
