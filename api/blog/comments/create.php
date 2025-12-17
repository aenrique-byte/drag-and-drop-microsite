<?php
/**
 * Blog Comment Create API
 * POST /api/blog/comments/create.php
 * 
 * Creates a new comment on a blog post.
 * Comments are set to 'pending' status by default and require moderation.
 * 
 * Request body:
 * - post_id (required): Blog post ID
 * - author_name (required): Commenter's name
 * - author_email (optional): Commenter's email
 * - content (required): Comment text
 * - parent_id (optional): Parent comment ID for replies
 * - website (honeypot): Should be empty - if filled, silently reject
 * - time_on_page (optional): Time spent on page in seconds (bot detection)
 */

require_once '../../bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Rate limiting: 5 comments per hour per IP
requireRateLimit('blog_comment_create', 5, 3600);

$input = json_decode(file_get_contents('php://input'), true);

// Honeypot check - if 'website' field is filled, it's a bot
// Silently accept but don't save (bots think they succeeded)
$honeypot = trim($input['website'] ?? '');
if (!empty($honeypot)) {
    // Fake success for bots
    echo json_encode([
        'success' => true,
        'message' => 'Comment submitted for moderation',
        'comment_id' => rand(1000, 9999) // Fake ID
    ]);
    exit;
}

// Time-on-page check (less than 3 seconds = likely bot)
$timeOnPage = (int)($input['time_on_page'] ?? 0);
$isLikelyBot = $timeOnPage > 0 && $timeOnPage < 3;

// Extract and validate required fields
$postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
$authorName = trim($input['author_name'] ?? '');
$authorEmail = trim($input['author_email'] ?? '');
$content = trim($input['content'] ?? '');
$parentId = isset($input['parent_id']) && $input['parent_id'] ? (int)$input['parent_id'] : null;

// Validation
$errors = [];

if (!$postId) {
    $errors[] = 'post_id is required';
}

if (empty($authorName)) {
    $errors[] = 'author_name is required';
} elseif (strlen($authorName) > 100) {
    $errors[] = 'author_name must be 100 characters or less';
}

if (!empty($authorEmail) && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

if (empty($content)) {
    $errors[] = 'content is required';
} elseif (strlen($content) > 5000) {
    $errors[] = 'Comment must be 5000 characters or less';
} elseif (strlen($content) < 2) {
    $errors[] = 'Comment must be at least 2 characters';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Check if post exists and is published
$stmt = $pdo->prepare("SELECT id, title, slug, status FROM blog_posts WHERE id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Blog post not found']);
    exit;
}

if ($post['status'] !== 'published') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Comments are not allowed on this post']);
    exit;
}

// If parent_id provided, verify it exists and belongs to this post
if ($parentId) {
    $stmt = $pdo->prepare("SELECT id, post_id, status FROM blog_comments WHERE id = ?");
    $stmt->execute([$parentId]);
    $parentComment = $stmt->fetch();
    
    if (!$parentComment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Parent comment not found']);
        exit;
    }
    
    if ($parentComment['post_id'] != $postId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Parent comment belongs to a different post']);
        exit;
    }
    
    // Only allow replies to approved comments
    if ($parentComment['status'] !== 'approved') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot reply to this comment']);
        exit;
    }
}

// Get IP and user agent
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$userAgentHash = hash('sha256', $userAgent);

// Generate content hash for duplicate detection
$contentHash = hash('sha256', $content . $authorName . $postId);

// Check for duplicate content (same content hash within 24 hours)
$stmt = $pdo->prepare("
    SELECT id FROM blog_comments 
    WHERE content_hash = ? 
    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stmt->execute([$contentHash]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Duplicate comment detected']);
    exit;
}

// Bot detection based on user agent
$knownBotSignatures = ['bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python', 'java', 'php'];
$isBot = false;
$userAgentLower = strtolower($userAgent);
foreach ($knownBotSignatures as $signature) {
    if (strpos($userAgentLower, $signature) !== false) {
        $isBot = true;
        break;
    }
}

// If time_on_page was suspiciously fast, mark as likely bot
if ($isLikelyBot) {
    $isBot = true;
}

// Sanitize content (strip HTML tags, allow basic formatting)
$sanitizedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

// Determine initial status
// Bots go straight to spam, normal comments are auto-approved (like chapter comments)
$status = $isBot ? 'spam' : 'approved';

try {
    $stmt = $pdo->prepare("
        INSERT INTO blog_comments (
            post_id, parent_id, author_name, author_email, content, content_hash,
            ip_address, user_agent, user_agent_hash, is_bot, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $postId,
        $parentId,
        $authorName,
        $authorEmail ?: null,
        $sanitizedContent,
        $contentHash,
        $ipAddress,
        substr($userAgent, 0, 500), // Limit user agent length
        $userAgentHash,
        $isBot ? 1 : 0,
        $status
    ]);
    
    $commentId = $pdo->lastInsertId();
    
    // Update comment count on blog_posts if not a bot
    if (!$isBot) {
        // Note: We count pending comments in the total for admin visibility
        // The trigger should handle this, but we can also update manually
        $pdo->prepare("
            UPDATE blog_posts 
            SET comment_count = (
                SELECT COUNT(*) FROM blog_comments 
                WHERE post_id = ? AND status IN ('approved', 'pending')
            )
            WHERE id = ?
        ")->execute([$postId, $postId]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $isBot 
            ? 'Comment submitted for moderation' // Same message for bots
            : 'Comment submitted successfully',
        'comment_id' => (int)$commentId,
        'status' => $status
    ]);
    
} catch (PDOException $e) {
    error_log("Blog comment creation failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to submit comment']);
}
