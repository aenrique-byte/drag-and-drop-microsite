-- ============================================================================
-- Add show_on_homepage field to stories table
-- Created: 2025-12-14
-- Description: Allows selecting which stories appear on the homepage grid
-- ============================================================================

-- Add show_on_homepage boolean field (default TRUE for backward compatibility)
ALTER TABLE stories
  ADD COLUMN IF NOT EXISTS show_on_homepage BOOLEAN DEFAULT TRUE;

-- Add index for efficient filtering
CREATE INDEX IF NOT EXISTS idx_stories_show_on_homepage ON stories(show_on_homepage);

-- ============================================================================
-- USAGE NOTES
-- ============================================================================
-- show_on_homepage: Controls whether a story appears in the homepage "Explore 
--                   the universes" grid section
--
-- Default is TRUE so existing stories continue to appear on homepage
-- Set to FALSE to hide a story from homepage while keeping it published
--
-- The homepage displays up to 4 stories, ordered by display_order ASC, id DESC
-- Stories with show_on_homepage = FALSE will be excluded from this list

-- ============================================================================
-- ROLLBACK INSTRUCTIONS (if needed)
-- ============================================================================
-- DROP INDEX IF EXISTS idx_stories_show_on_homepage ON stories;
-- ALTER TABLE stories DROP COLUMN IF EXISTS show_on_homepage;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
