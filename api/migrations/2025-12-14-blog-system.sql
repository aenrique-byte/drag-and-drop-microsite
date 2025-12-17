-- =====================================================
-- BLOG SYSTEM DATABASE SCHEMA
-- =====================================================
-- Migration for the Author CMS Blog System
-- Based on blog_implementation_plan.md
--
-- Tables:
-- 1. blog_posts - Main blog posts with dual storage (JSON + HTML)
-- 2. blog_categories - Post categorization
-- 3. blog_images - TipTap content images
-- 4. blog_content_images - Gallery integration tracking
-- 5. blog_comments - Comments with spam protection
-- 6. blog_analytics - Raw analytics events (90-day retention)
-- 7. blog_analytics_daily - Daily aggregates (permanent)
-- 8. blog_revisions - Post revision history
-- 9. social_api_credentials - Encrypted social media tokens
-- 10. blog_crosspost_settings - Per-post crosspost config
-- 11. blog_social_posts - Social media post tracking
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. BLOG POSTS TABLE
-- =====================================================
-- Main blog posts with dual content storage:
-- content_json for TipTap editor re-opening
-- content_html for frontend rendering

CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  
  -- DUAL STORAGE: JSON for editor, HTML for rendering
  `content_json` longtext NOT NULL COMMENT 'TipTap JSON for editor re-opening',
  `content_html` longtext NOT NULL COMMENT 'Sanitized HTML for frontend rendering',
  
  -- Cover/Featured images
  `cover_image` varchar(500) DEFAULT NULL COMMENT 'Legacy path field for backward compat',
  `featured_image_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK to images table (gallery integration)',
  
  -- Social media specific images (user must upload correct dimensions)
  `instagram_image` varchar(500) DEFAULT NULL COMMENT 'User-uploaded 1080x1080 or 1080x1350',
  `instagram_image_id` int(10) UNSIGNED DEFAULT NULL,
  `twitter_image` varchar(500) DEFAULT NULL COMMENT 'User-uploaded 1200x675',
  `twitter_image_id` int(10) UNSIGNED DEFAULT NULL,
  `facebook_image` varchar(500) DEFAULT NULL COMMENT 'User-uploaded 1200x630',
  `facebook_image_id` int(10) UNSIGNED DEFAULT NULL,
  
  -- OpenGraph metadata
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` text DEFAULT NULL,
  
  -- SEO fields
  `meta_description` text DEFAULT NULL,
  `primary_keywords` varchar(500) DEFAULT NULL,
  `longtail_keywords` text DEFAULT NULL,
  
  -- Categorization
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of tag strings' CHECK (json_valid(`tags`)),
  `categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'JSON array of category strings' CHECK (json_valid(`categories`)),
  `universe_tag` varchar(255) DEFAULT NULL COMMENT 'Associated story universe (Destiny, Sinbad, etc.)',
  
  -- Publishing
  `author_id` int(10) UNSIGNED NOT NULL,
  `status` enum('draft','published','scheduled') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  
  -- Post metadata (aggregated from analytics daily)
  `reading_time` int(10) UNSIGNED DEFAULT NULL COMMENT 'Estimated reading time in minutes',
  `view_count` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Synced from daily aggregates',
  `like_count` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Synced from daily aggregates',
  `comment_count` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Auto-updated via trigger',
  
  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_blog_posts_status_published` (`status`, `published_at`),
  KEY `idx_blog_posts_universe` (`universe_tag`),
  KEY `idx_blog_posts_author` (`author_id`),
  KEY `idx_blog_posts_scheduled` (`scheduled_at`),
  KEY `idx_blog_posts_created` (`created_at`),
  CONSTRAINT `fk_blog_posts_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_posts_featured_image` FOREIGN KEY (`featured_image_id`) REFERENCES `images` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_blog_posts_instagram_image` FOREIGN KEY (`instagram_image_id`) REFERENCES `images` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_blog_posts_twitter_image` FOREIGN KEY (`twitter_image_id`) REFERENCES `images` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_blog_posts_facebook_image` FOREIGN KEY (`facebook_image_id`) REFERENCES `images` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Blog posts with dual content storage (JSON + HTML)';

-- =====================================================
-- 2. BLOG CATEGORIES TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS `blog_categories` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_blog_categories_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Blog post categories for filtering';

-- =====================================================
-- 3. BLOG IMAGES TABLE
-- =====================================================
-- For TipTap image uploads within content (standalone, not gallery-linked)

CREATE TABLE IF NOT EXISTS `blog_images` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL if orphaned/unassigned',
  `url` varchar(500) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `width` int(10) UNSIGNED DEFAULT NULL,
  `height` int(10) UNSIGNED DEFAULT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL COMMENT 'Size in bytes',
  `mime_type` varchar(50) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blog_images_post` (`post_id`),
  KEY `idx_blog_images_uploaded` (`uploaded_at`),
  CONSTRAINT `fk_blog_images_post` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Standalone blog images (not gallery-linked)';

-- =====================================================
-- 4. BLOG CONTENT IMAGES TABLE
-- =====================================================
-- Tracks gallery images used in blog posts (Phase 5 integration)
-- Enables: orphan cleanup, usage analytics, cross-linking

CREATE TABLE IF NOT EXISTS `blog_content_images` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_post_id` int(10) UNSIGNED NOT NULL,
  `image_id` int(10) UNSIGNED NOT NULL COMMENT 'FK to images table (gallery system)',
  `source` enum('inline','featured','social_instagram','social_twitter','social_facebook') NOT NULL DEFAULT 'inline' COMMENT 'Where image is used in post',
  `position_order` int(11) NOT NULL DEFAULT 0 COMMENT 'Order in post content (for inline images)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_image_source` (`blog_post_id`, `image_id`, `source`),
  KEY `idx_blog_content_images_source` (`source`),
  KEY `idx_blog_content_images_image` (`image_id`),
  CONSTRAINT `fk_blog_content_images_post` FOREIGN KEY (`blog_post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_content_images_image` FOREIGN KEY (`image_id`) REFERENCES `images` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks gallery images used in blog posts';

-- =====================================================
-- 5. BLOG COMMENTS TABLE
-- =====================================================
-- Comments with threaded replies and spam protection

CREATE TABLE IF NOT EXISTS `blog_comments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'For threaded replies',
  `author_name` varchar(100) NOT NULL,
  `author_email` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  
  -- Spam protection
  `content_hash` varchar(64) DEFAULT NULL COMMENT 'SHA256 hash for duplicate detection',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `user_agent_hash` varchar(64) DEFAULT NULL,
  `is_bot` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Detected bot/crawler',
  `is_flagged` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'User-flagged for review',
  
  -- Moderation
  `status` enum('pending','approved','spam','trash') NOT NULL DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  
  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `idx_blog_comments_post` (`post_id`),
  KEY `idx_blog_comments_parent` (`parent_id`),
  KEY `idx_blog_comments_status` (`status`),
  KEY `idx_blog_comments_ip` (`ip_address`),
  KEY `idx_blog_comments_content_hash` (`content_hash`),
  KEY `idx_blog_comments_created` (`created_at`),
  CONSTRAINT `fk_blog_comments_post` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `blog_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_comments_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Blog comments with threaded replies and spam protection';

-- =====================================================
-- 6. BLOG ANALYTICS TABLE (Raw Events)
-- =====================================================
-- Raw analytics events - retained for 90 days
-- Rolled up daily into blog_analytics_daily

CREATE TABLE IF NOT EXISTS `blog_analytics` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` int(10) UNSIGNED NOT NULL,
  `event_type` enum('view','like','share','comment') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `referer` varchar(500) DEFAULT NULL,
  `country_code` varchar(2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blog_analytics_post_event` (`post_id`, `event_type`),
  KEY `idx_blog_analytics_created` (`created_at`),
  KEY `idx_blog_analytics_country` (`country_code`),
  CONSTRAINT `fk_blog_analytics_post` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Raw blog analytics events (90-day retention)';

-- =====================================================
-- 7. BLOG ANALYTICS DAILY TABLE (Aggregates)
-- =====================================================
-- Daily aggregates - permanent storage
-- Populated by daily cron job from blog_analytics

CREATE TABLE IF NOT EXISTS `blog_analytics_daily` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `views` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `likes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `shares` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `comments` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `unique_visitors` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Count of unique IPs',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_date` (`post_id`, `date`),
  KEY `idx_blog_analytics_daily_date` (`date`),
  KEY `idx_blog_analytics_daily_post` (`post_id`),
  CONSTRAINT `fk_blog_analytics_daily_post` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily aggregated blog analytics (permanent)';

-- =====================================================
-- 8. BLOG REVISIONS TABLE
-- =====================================================
-- Post revision history for content changes

CREATE TABLE IF NOT EXISTS `blog_revisions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` int(10) UNSIGNED NOT NULL,
  `content_json` longtext NOT NULL COMMENT 'TipTap JSON snapshot',
  `content_html` longtext NOT NULL COMMENT 'HTML snapshot',
  `title` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `edited_by` int(10) UNSIGNED NOT NULL,
  `change_summary` varchar(255) DEFAULT NULL COMMENT 'Brief description of changes',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_blog_revisions_post` (`post_id`),
  KEY `idx_blog_revisions_created` (`created_at`),
  KEY `idx_blog_revisions_editor` (`edited_by`),
  CONSTRAINT `fk_blog_revisions_post` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blog_revisions_editor` FOREIGN KEY (`edited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Blog post revision history';

-- =====================================================
-- 9. SOCIAL API CREDENTIALS TABLE
-- =====================================================
-- Encrypted storage for social media API tokens

CREATE TABLE IF NOT EXISTS `social_api_credentials` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `platform` enum('instagram','twitter','facebook','discord','threads','bluesky','youtube') NOT NULL,
  `access_token` text DEFAULT NULL COMMENT 'Encrypted token',
  `refresh_token` text DEFAULT NULL COMMENT 'Encrypted refresh token',
  `token_expires_at` datetime DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Platform-specific config (webhooks, app IDs, etc.)' CHECK (json_valid(`config`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform` (`platform`),
  KEY `idx_social_api_credentials_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Encrypted social media API credentials';

-- =====================================================
-- 10. BLOG CROSSPOST SETTINGS TABLE
-- =====================================================
-- Per-post crosspost configuration for each platform

CREATE TABLE IF NOT EXISTS `blog_crosspost_settings` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_post_id` int(10) UNSIGNED NOT NULL,
  `platform` enum('instagram','twitter','facebook','discord','threads','bluesky','youtube') NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `custom_message` text DEFAULT NULL COMMENT 'Platform-specific message override',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_platform` (`blog_post_id`, `platform`),
  KEY `idx_blog_crosspost_enabled` (`enabled`),
  KEY `idx_blog_crosspost_platform` (`platform`),
  CONSTRAINT `fk_blog_crosspost_post` FOREIGN KEY (`blog_post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-post crosspost settings by platform';

-- =====================================================
-- 11. BLOG SOCIAL POSTS TABLE
-- =====================================================
-- Tracking for social media crossposted content

CREATE TABLE IF NOT EXISTS `blog_social_posts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `blog_post_id` int(10) UNSIGNED NOT NULL,
  `platform` enum('instagram','twitter','facebook','discord','threads','bluesky','youtube') NOT NULL,
  `platform_post_id` varchar(255) DEFAULT NULL COMMENT 'ID from the social platform',
  `post_url` varchar(500) DEFAULT NULL,
  `status` enum('pending','success','failed') NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `retry_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `posted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_post_platform` (`blog_post_id`, `platform`),
  KEY `idx_blog_social_posts_status` (`status`),
  KEY `idx_blog_social_posts_platform` (`platform`),
  KEY `idx_blog_social_posts_posted` (`posted_at`),
  CONSTRAINT `fk_blog_social_posts_post` FOREIGN KEY (`blog_post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Social media crosspost tracking';

-- =====================================================
-- TRIGGERS FOR AUTOMATIC COUNTERS
-- =====================================================

DELIMITER $$

-- Blog comment count triggers (only count approved comments)
CREATE TRIGGER `blog_comments_insert` AFTER INSERT ON `blog_comments`
FOR EACH ROW BEGIN
  IF NEW.status = 'approved' THEN
    UPDATE blog_posts SET comment_count = comment_count + 1 WHERE id = NEW.post_id;
  END IF;
END$$

CREATE TRIGGER `blog_comments_update` AFTER UPDATE ON `blog_comments`
FOR EACH ROW BEGIN
  -- Comment was approved
  IF OLD.status != 'approved' AND NEW.status = 'approved' THEN
    UPDATE blog_posts SET comment_count = comment_count + 1 WHERE id = NEW.post_id;
  END IF;
  -- Comment was unapproved
  IF OLD.status = 'approved' AND NEW.status != 'approved' THEN
    UPDATE blog_posts SET comment_count = GREATEST(0, comment_count - 1) WHERE id = NEW.post_id;
  END IF;
END$$

CREATE TRIGGER `blog_comments_delete` AFTER DELETE ON `blog_comments`
FOR EACH ROW BEGIN
  IF OLD.status = 'approved' THEN
    UPDATE blog_posts SET comment_count = GREATEST(0, comment_count - 1) WHERE id = OLD.post_id;
  END IF;
END$$

DELIMITER ;

-- =====================================================
-- SEED DATA: DEFAULT CATEGORIES
-- =====================================================

INSERT INTO `blog_categories` (`slug`, `name`, `description`, `sort_order`) VALUES
('announcements', 'Announcements', 'News, updates, and important announcements', 1),
('dev-logs', 'Dev Logs', 'Development updates and behind-the-scenes content', 2),
('worldbuilding', 'Worldbuilding', 'Lore, world history, and universe exploration', 3),
('writing-process', 'Writing Process', 'Insights into the creative writing journey', 4),
('chapter-notes', 'Chapter Notes', 'Commentary and notes about released chapters', 5),
('litrpg-mechanics', 'LitRPG Mechanics', 'Game system breakdowns and explanations', 6),
('character-spotlights', 'Character Spotlights', 'Deep dives into character backgrounds', 7),
('reader-qa', 'Reader Q&A', 'Answers to reader questions and discussions', 8)
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`), `sort_order` = VALUES(`sort_order`);

-- =====================================================
-- CREATE BLOG-ASSETS GALLERY (Phase 5 Gallery Integration)
-- =====================================================
-- This creates a dedicated gallery for blog images
-- Must be run after galleries table exists

INSERT INTO `galleries` (`slug`, `title`, `description`, `status`, `created_by`)
SELECT 'blog-assets', 'Blog Assets', 'Centralized storage for blog post images', 'published', 1
WHERE NOT EXISTS (SELECT 1 FROM `galleries` WHERE `slug` = 'blog-assets');

-- =====================================================
-- ADD ASPECT_RATIO COLUMN TO IMAGES TABLE (Phase 5)
-- =====================================================
-- Enables fast filtering by aspect ratio in image picker

ALTER TABLE `images`
ADD COLUMN IF NOT EXISTS `aspect_ratio` FLOAT GENERATED ALWAYS AS (`width` / NULLIF(`height`, 0)) STORED COMMENT 'Auto-calculated aspect ratio for filtering';

-- Add index for aspect ratio filtering (ignore if exists)
-- Note: MySQL 8.0+ supports IF NOT EXISTS for indexes
-- For older versions, you may need to check manually or wrap in a procedure

-- =====================================================
-- ENABLE FOREIGN KEY CHECKS
-- =====================================================

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================
-- 
-- Next Steps:
-- 1. Run this migration: mysql -u user -p database < 2025-12-14-blog-system.sql
-- 2. Verify tables were created: SHOW TABLES LIKE 'blog_%';
-- 3. Verify categories were seeded: SELECT * FROM blog_categories;
-- 4. Verify blog-assets gallery exists: SELECT * FROM galleries WHERE slug = 'blog-assets';
--
-- Cron Jobs to Set Up:
-- - Daily analytics rollup: api/cron/rollup-blog-analytics.php (2 AM daily)
-- - Scheduled post publishing: api/cron/publish-scheduled-blog-posts.php (every 5 min)
-- - Analytics cleanup (90-day retention): api/cron/cleanup-blog-analytics.php (weekly)
--
-- =====================================================
