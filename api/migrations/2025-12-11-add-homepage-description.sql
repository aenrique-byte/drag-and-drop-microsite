-- ============================================================================
-- Add homepage_description field to stories table
-- Created: 2025-12-11
-- Description: Adds a homepage_description field for simplified descriptions
--              on the homepage, while keeping the full markdown description
--              for the story pages
-- ============================================================================

-- Add homepage_description column to stories table
ALTER TABLE stories
  ADD COLUMN IF NOT EXISTS homepage_description TEXT DEFAULT NULL;

-- Optional: Create an index if you plan to search by this field
-- CREATE INDEX IF NOT EXISTS idx_stories_homepage_description ON stories (homepage_description(255));

-- ============================================================================
-- USAGE NOTES
-- ============================================================================
-- homepage_description: Plain text or simplified description for homepage cards
-- description: Full markdown description with color tags for story pages
-- If homepage_description is NULL, the homepage can fall back to description

-- ============================================================================
-- ROLLBACK INSTRUCTIONS (if needed)
-- ============================================================================
-- ALTER TABLE stories DROP COLUMN IF EXISTS homepage_description;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
