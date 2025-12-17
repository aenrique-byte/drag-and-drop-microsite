<?php
/**
 * Blog Analytics Details API
 * 
 * Returns analytics data for blog posts, similar to story-details.php
 * 
 * GET /api/admin/analytics/blog-details.php
 *   ?days=30           - Time period (default: 30)
 *   &post_id=123       - Optional: Get details for specific post
 */

require_once '../../bootstrap.php';

header('Content-Type: application/json');

// Require admin authentication
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$days = max(1, min(365, $days)); // Clamp to 1-365

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : null;

try {
    if ($post_id) {
        // Get details for a specific blog post
        
        // First, get the post info
        $stmt = $pdo->prepare("
            SELECT id, slug, title, status, view_count, like_count, comment_count,
                   created_at, published_at
            FROM blog_posts 
            WHERE id = ?
        ");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$post) {
            http_response_code(404);
            echo json_encode(['error' => 'Blog post not found']);
            exit;
        }
        
        // Get analytics for this post
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                SUM(CASE WHEN event_type = 'blog_view' THEN 1 ELSE 0 END) as views,
                SUM(CASE WHEN event_type = 'blog_like' THEN 1 ELSE 0 END) as likes,
                SUM(CASE WHEN event_type = 'blog_share' THEN 1 ELSE 0 END) as shares,
                COUNT(DISTINCT ip_hash) as unique_visitors
            FROM analytics_events
            WHERE content_type = 'blog_post'
            AND content_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$post_id, $days]);
        $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total stats for the period
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN event_type = 'blog_view' THEN 1 ELSE 0 END) as total_views,
                SUM(CASE WHEN event_type = 'blog_like' THEN 1 ELSE 0 END) as total_likes,
                SUM(CASE WHEN event_type = 'blog_share' THEN 1 ELSE 0 END) as total_shares,
                COUNT(DISTINCT ip_hash) as unique_visitors
            FROM analytics_events
            WHERE content_type = 'blog_post'
            AND content_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$post_id, $days]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get top referrers
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(
                    CASE 
                        WHEN referrer IS NULL OR referrer = '' THEN 'Direct'
                        WHEN referrer LIKE '%google%' THEN 'Google'
                        WHEN referrer LIKE '%facebook%' THEN 'Facebook'
                        WHEN referrer LIKE '%twitter%' OR referrer LIKE '%t.co%' THEN 'Twitter/X'
                        WHEN referrer LIKE '%instagram%' THEN 'Instagram'
                        WHEN referrer LIKE '%linkedin%' THEN 'LinkedIn'
                        ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '://', -1), '/', 1)
                    END,
                    'Direct'
                ) as source,
                COUNT(*) as count,
                COUNT(DISTINCT ip_hash) as unique_visitors
            FROM analytics_events
            WHERE content_type = 'blog_post'
            AND content_id = ?
            AND event_type = 'blog_view'
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY source
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute([$post_id, $days]);
        $referrers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get geographic breakdown
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(country_code, 'Unknown') as country_code,
                COUNT(*) as views,
                COUNT(DISTINCT ip_hash) as unique_visitors
            FROM analytics_events
            WHERE content_type = 'blog_post'
            AND content_id = ?
            AND event_type = 'blog_view'
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY country_code
            ORDER BY views DESC
            LIMIT 10
        ");
        $stmt->execute([$post_id, $days]);
        $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'post' => $post,
                'totals' => $totals,
                'daily_stats' => $daily_stats,
                'referrers' => $referrers,
                'countries' => $countries,
                'period_days' => $days
            ]
        ]);
        
    } else {
        // Get overview of all blog posts
        
        $stmt = $pdo->prepare("
            SELECT 
                bp.id,
                bp.slug,
                bp.title,
                bp.status,
                bp.view_count,
                bp.like_count,
                bp.comment_count,
                bp.reading_time,
                bp.published_at,
                bp.created_at,
                COALESCE(analytics.period_views, 0) as period_views,
                COALESCE(analytics.period_likes, 0) as period_likes,
                COALESCE(analytics.period_shares, 0) as period_shares,
                COALESCE(analytics.unique_visitors, 0) as unique_visitors
            FROM blog_posts bp
            LEFT JOIN (
                SELECT 
                    content_id,
                    SUM(CASE WHEN event_type = 'blog_view' THEN 1 ELSE 0 END) as period_views,
                    SUM(CASE WHEN event_type = 'blog_like' THEN 1 ELSE 0 END) as period_likes,
                    SUM(CASE WHEN event_type = 'blog_share' THEN 1 ELSE 0 END) as period_shares,
                    COUNT(DISTINCT ip_hash) as unique_visitors
                FROM analytics_events
                WHERE content_type = 'blog_post'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY content_id
            ) analytics ON bp.id = analytics.content_id
            ORDER BY period_views DESC, bp.view_count DESC
        ");
        $stmt->execute([$days]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get overall blog statistics
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN event_type = 'blog_view' THEN 1 ELSE 0 END) as total_views,
                SUM(CASE WHEN event_type = 'blog_like' THEN 1 ELSE 0 END) as total_likes,
                SUM(CASE WHEN event_type = 'blog_share' THEN 1 ELSE 0 END) as total_shares,
                COUNT(DISTINCT ip_hash) as unique_visitors,
                COUNT(DISTINCT content_id) as posts_with_views
            FROM analytics_events
            WHERE content_type = 'blog_post'
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get daily trend
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                SUM(CASE WHEN event_type = 'blog_view' THEN 1 ELSE 0 END) as views,
                SUM(CASE WHEN event_type = 'blog_like' THEN 1 ELSE 0 END) as likes,
                COUNT(DISTINCT ip_hash) as unique_visitors
            FROM analytics_events
            WHERE content_type = 'blog_post'
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$days]);
        $daily_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total published posts count
        $stmt = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'");
        $published_count = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'draft'");
        $draft_count = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'scheduled'");
        $scheduled_count = $stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'posts' => $posts,
                'summary' => array_merge($summary ?: [], [
                    'published_count' => intval($published_count),
                    'draft_count' => intval($draft_count),
                    'scheduled_count' => intval($scheduled_count)
                ]),
                'daily_trend' => $daily_trend,
                'period_days' => $days
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Blog analytics error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch blog analytics']);
}
