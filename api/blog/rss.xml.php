<?php
/**
 * Blog RSS Feed
 * 
 * GET /api/blog/rss.xml.php
 * 
 * Generates RSS 2.0 feed for published blog posts
 * 
 * Query Parameters:
 * - limit: int (default: 20, max: 50)
 * - category: string (optional) - Filter by category
 * - universe: string (optional) - Filter by universe
 */

require_once '../bootstrap.php';

// Rate limit: 30 requests/minute
requireRateLimit('blog:rss', 30, 60);

try {
    $pdo = db();
    
    $limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 20;
    $category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : null;
    $universe = isset($_GET['universe']) ? sanitizeInput($_GET['universe']) : null;
    
    // Build WHERE clause
    $where = ["bp.status = 'published'", "bp.published_at <= NOW()"];
    $params = [];
    
    if ($category) {
        $where[] = "JSON_CONTAINS(bp.categories, ?, '$')";
        $params[] = json_encode($category);
    }
    
    if ($universe) {
        $where[] = "bp.universe_tag = ?";
        $params[] = $universe;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // Fetch posts
    $sql = "
        SELECT 
            bp.id,
            bp.slug,
            bp.title,
            bp.excerpt,
            bp.content_html,
            bp.cover_image,
            bp.tags,
            bp.categories,
            bp.universe_tag,
            bp.published_at,
            bp.updated_at,
            u.username as author_name
        FROM blog_posts bp
        LEFT JOIN users u ON bp.author_id = u.id
        WHERE {$whereClause}
        ORDER BY bp.published_at DESC
        LIMIT ?
    ";
    
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();
    
    // Get site info for RSS metadata
    $siteStmt = $pdo->query("SELECT * FROM site_config ORDER BY id LIMIT 1");
    $siteConfig = $siteStmt->fetch();
    
    $authorStmt = $pdo->query("SELECT * FROM author_profile ORDER BY id LIMIT 1");
    $author = $authorStmt->fetch();
    
    // Determine base URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host;
    
    // Site metadata
    $siteTitle = $author['name'] ?? 'Author Blog';
    $siteDescription = $siteConfig['description'] ?? $author['bio'] ?? 'Latest updates and posts';
    
    // Set RSS content type
    header('Content-Type: application/rss+xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
    
    // Generate RSS
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    ?>
<rss version="2.0" 
     xmlns:content="http://purl.org/rss/1.0/modules/content/" 
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:media="http://search.yahoo.com/mrss/">
<channel>
    <title><?php echo htmlspecialchars($siteTitle . ' Blog'); ?></title>
    <link><?php echo htmlspecialchars($baseUrl . '/blog'); ?></link>
    <description><?php echo htmlspecialchars($siteDescription); ?></description>
    <language>en-us</language>
    <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
    <atom:link href="<?php echo htmlspecialchars($baseUrl . '/api/blog/rss.xml.php'); ?>" rel="self" type="application/rss+xml"/>
    <generator>Author CMS Blog</generator>
<?php if ($author && !empty($author['portrait_image'])): ?>
    <image>
        <url><?php echo htmlspecialchars($baseUrl . $author['portrait_image']); ?></url>
        <title><?php echo htmlspecialchars($siteTitle . ' Blog'); ?></title>
        <link><?php echo htmlspecialchars($baseUrl . '/blog'); ?></link>
    </image>
<?php endif; ?>
<?php foreach ($posts as $post): ?>
<?php 
    $postUrl = $baseUrl . '/blog/' . $post['slug'];
    $pubDate = date('r', strtotime($post['published_at']));
    $tags = !empty($post['tags']) ? json_decode($post['tags'], true) : [];
    $categories = !empty($post['categories']) ? json_decode($post['categories'], true) : [];
    
    // Use excerpt or truncated content for description
    $description = $post['excerpt'] ?: substr(strip_tags($post['content_html']), 0, 300) . '...';
?>
    <item>
        <title><?php echo htmlspecialchars($post['title']); ?></title>
        <link><?php echo htmlspecialchars($postUrl); ?></link>
        <description><?php echo htmlspecialchars($description); ?></description>
        <content:encoded><![CDATA[<?php echo $post['content_html']; ?>]]></content:encoded>
        <pubDate><?php echo $pubDate; ?></pubDate>
        <guid isPermaLink="true"><?php echo htmlspecialchars($postUrl); ?></guid>
<?php if ($post['author_name']): ?>
        <dc:creator><?php echo htmlspecialchars($post['author_name']); ?></dc:creator>
<?php endif; ?>
<?php foreach ($categories as $cat): ?>
        <category><?php echo htmlspecialchars($cat); ?></category>
<?php endforeach; ?>
<?php foreach ($tags as $tag): ?>
        <category><?php echo htmlspecialchars($tag); ?></category>
<?php endforeach; ?>
<?php if ($post['cover_image']): ?>
        <media:content url="<?php echo htmlspecialchars($baseUrl . $post['cover_image']); ?>" medium="image"/>
        <enclosure url="<?php echo htmlspecialchars($baseUrl . $post['cover_image']); ?>" type="image/jpeg"/>
<?php endif; ?>
    </item>
<?php endforeach; ?>
</channel>
</rss>
<?php

} catch (Exception $e) {
    error_log("Blog RSS error: " . $e->getMessage());
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo 'Failed to generate RSS feed';
}
