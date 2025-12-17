<?php
require_once 'bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: max-age=3600'); // 1 hour

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

$BASE_URL = getBaseUrl($pdo);

// Safer escaper for XML text nodes
function x($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Helper to emit one <url> block
function url($loc, $lastmod = null, $priority = null, $changefreq = null) {
    echo "<url>";
    echo "<loc>", x($loc), "</loc>";
    if ($lastmod)   echo "<lastmod>", x(date('Y-m-d', strtotime($lastmod))), "</lastmod>";
    if ($priority)  echo "<priority>", x($priority), "</priority>";
    if ($changefreq)echo "<changefreq>", x($changefreq), "</changefreq>";
    echo "</url>";
}

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

try {
    // Default fetch mode
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Static pages
    url($BASE_URL . '/', null, '1.0', 'weekly');
    url($BASE_URL . '/storytime', null, '0.9', 'weekly');
    url($BASE_URL . '/galleries', null, '0.6', 'weekly');

    // Stories (published)
    $stmt = $pdo->prepare("SELECT slug, updated_at FROM stories WHERE status = 'published' ORDER BY updated_at DESC");
    $stmt->execute();
    while ($s = $stmt->fetch()) {
        url($BASE_URL . '/storytime/story/' . $s['slug'], $s['updated_at'], '0.8', 'monthly');
    }

    // Chapters (published) — single optimized query
    $stmt = $pdo->prepare("
        SELECT c.slug AS chapter_slug, c.updated_at, s.slug AS story_slug
        FROM chapters c
        JOIN stories s ON c.story_id = s.id
        WHERE c.status = 'published' AND s.status = 'published'
        ORDER BY s.slug, c.chapter_number
    ");
    $stmt->execute();
    while ($c = $stmt->fetch()) {
        url($BASE_URL . '/storytime/story/' . $c['story_slug'] . '/' . $c['chapter_slug'], $c['updated_at'], '0.7', 'never');
    }

    // Galleries (published only)
    $stmt = $pdo->prepare("SELECT slug, updated_at FROM galleries WHERE status = 'published' ORDER BY updated_at DESC");
    $stmt->execute();
    while ($g = $stmt->fetch()) {
        url($BASE_URL . '/galleries/' . $g['slug'], $g['updated_at'], '0.5', 'monthly');
    }

} catch (Throwable $e) {
    error_log("Sitemap generation error: " . $e->getMessage());
    // Fallback to minimal entries so bots still get a valid XML
    url($BASE_URL . '/', null, '1.0', 'weekly');
    url($BASE_URL . '/storytime', null, '0.9', 'weekly');
    url($BASE_URL . '/galleries', null, '0.6', 'weekly');
}

echo '</urlset>';
?>
