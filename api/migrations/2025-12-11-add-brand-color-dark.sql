-- ============================================================================
-- Add brand_color_dark to homepage_settings
-- Created: 2025-12-11
-- Description: Adds a separate brand color for dark mode
-- ============================================================================

ALTER TABLE homepage_settings
  ADD COLUMN IF NOT EXISTS brand_color_dark VARCHAR(7) DEFAULT '#10b981';

-- Update existing rows to have a default dark color
UPDATE homepage_settings
SET brand_color_dark = '#10b981'
WHERE brand_color_dark IS NULL;

-- ============================================================================
-- USAGE NOTES
-- ============================================================================
-- brand_color: Brand color for light theme (default: #10b981)
-- brand_color_dark: Brand color for dark theme (default: #10b981)

-- ============================================================================
-- ROLLBACK INSTRUCTIONS (if needed)
-- ============================================================================
-- ALTER TABLE homepage_settings DROP COLUMN IF EXISTS brand_color_dark;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
