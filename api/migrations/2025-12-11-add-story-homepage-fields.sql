-- ============================================================================
-- Add homepage display fields to stories table
-- Created: 2025-12-11
-- Description: Adds tagline field for homepage display
-- ============================================================================

-- Add tagline field
ALTER TABLE stories
  ADD COLUMN IF NOT EXISTS tagline VARCHAR(255) DEFAULT NULL;

-- ============================================================================
-- USAGE NOTES
-- ============================================================================
-- tagline: Short catchy tagline shown below the title on homepage
--
-- Other homepage data comes from existing fields:
-- - external_links: RoyalRoad, Patreon, etc. (existing JSON column)
-- - genres: Story genres (existing JSON column)
-- - primary_keywords, longtail_keywords, target_audience: SEO fields (existing)
-- - latest chapter info: Pulled dynamically from RoyalRoad API

-- ============================================================================
-- ROLLBACK INSTRUCTIONS (if needed)
-- ============================================================================
-- ALTER TABLE stories DROP COLUMN IF EXISTS tagline;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
