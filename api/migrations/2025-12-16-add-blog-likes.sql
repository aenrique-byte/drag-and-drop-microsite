-- Blog Likes Table
-- Mirrors chapter_likes structure for consistency
-- Stores persistent likes with IP + user agent deduplication

CREATE TABLE IF NOT EXISTS blog_likes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent_hash VARCHAR(64) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (post_id, ip_address, user_agent_hash),
    KEY idx_post_id (post_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sync existing like counts from blog_posts table if needed
-- (They were being tracked in analytics_events, but this is the permanent storage)
