-- =====================================================
-- ADD FIRST_PUBLISHED_AT TO BLOG_POSTS
-- =====================================================
-- Tracks when a post was FIRST published (never changes)
-- Used to prevent social media spam on updates
--
-- Logic:
-- - first_published_at is set ONCE when status changes to 'published'
-- - published_at can change on republish/schedule changes
-- - Crossposting only happens if first_published_at is NULL (first publish)
-- =====================================================

ALTER TABLE `blog_posts`
ADD COLUMN IF NOT EXISTS `first_published_at` datetime DEFAULT NULL 
COMMENT 'Set once on first publish - never changes. Used to prevent social media spam on updates.'
AFTER `published_at`;

-- Add index for efficient querying
CREATE INDEX IF NOT EXISTS `idx_blog_posts_first_published` ON `blog_posts` (`first_published_at`);

-- =====================================================
-- MIGRATION NOTES
-- =====================================================
-- After running this migration:
-- 
-- 1. For existing published posts that don't have first_published_at set:
--    UPDATE blog_posts SET first_published_at = published_at 
--    WHERE status = 'published' AND first_published_at IS NULL AND published_at IS NOT NULL;
--
-- 2. The PHP logic should:
--    - On publish: Check if first_published_at is NULL
--      - If NULL: Set first_published_at = NOW() and allow crossposting
--      - If NOT NULL: This is an update, skip crossposting
--
-- 3. Frontend (BlogManager.tsx) should:
--    - Show crosspost toggles only if first_published_at is NULL (new post)
--    - Show "Already posted" badges if crosspost exists in blog_social_posts
--    - Offer manual "Re-announce" option if intentional re-crosspost is needed
-- =====================================================
