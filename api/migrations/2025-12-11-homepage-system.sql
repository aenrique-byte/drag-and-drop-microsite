-- ============================================================================
-- Homepage System Migration
-- Created: 2025-12-11
-- Description: Creates tables and extends existing schemas for the new
--              Universe Portal homepage system
-- ============================================================================

-- ----------------------------------------------------------------------------
-- NEW TABLE: homepage_settings
-- Single source of truth for ALL homepage configuration
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS homepage_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,

  -- Hero section
  hero_title VARCHAR(255) DEFAULT 'Step into the worlds of',
  hero_tagline VARCHAR(255) DEFAULT 'Shared Multiverse Portal',
  hero_description TEXT,

  -- Featured story (no foreign key constraint - more flexible)
  featured_story_id INT DEFAULT NULL,
  show_featured_story BOOLEAN DEFAULT TRUE,

  -- Section toggles
  show_activity_feed BOOLEAN DEFAULT TRUE,
  show_tools_section BOOLEAN DEFAULT TRUE,

  -- Newsletter
  newsletter_cta_text VARCHAR(255) DEFAULT 'Join the Newsletter',
  newsletter_url VARCHAR(500),

  -- Branding
  brand_color VARCHAR(7) DEFAULT '#10b981',

  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ----------------------------------------------------------------------------
-- NEW TABLE: homepage_tools
-- Configurable tools/features sidebar
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS homepage_tools (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  icon VARCHAR(50) DEFAULT '🔧',
  link VARCHAR(500),
  display_order INT DEFAULT 0,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_display_order (display_order)
);

-- ----------------------------------------------------------------------------
-- NEW TABLE: activity_feed
-- Generic activity/updates feed
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_feed (
  id INT PRIMARY KEY AUTO_INCREMENT,
  type ENUM('blog', 'chapter', 'announcement', 'misc') DEFAULT 'misc',
  source VARCHAR(50) NOT NULL,
  label VARCHAR(100),
  title VARCHAR(255) NOT NULL,
  series_title VARCHAR(255),
  url VARCHAR(500),
  published_at TIMESTAMP NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_published (published_at DESC, is_active)
);

-- ----------------------------------------------------------------------------
-- EXTEND TABLE: stories
-- Add fields for external platform links, ordering, and featured story
-- ----------------------------------------------------------------------------
ALTER TABLE stories
  ADD COLUMN IF NOT EXISTS continue_url_royalroad VARCHAR(500),
  ADD COLUMN IF NOT EXISTS continue_url_patreon VARCHAR(500),
  ADD COLUMN IF NOT EXISTS latest_chapter_number INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS latest_chapter_title VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS teaser_chapter_count INT DEFAULT 20,
  ADD COLUMN IF NOT EXISTS genre_tags VARCHAR(500),
  ADD COLUMN IF NOT EXISTS cta_text VARCHAR(100) DEFAULT 'Start Reading',
  ADD COLUMN IF NOT EXISTS is_featured BOOLEAN DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS display_order INT DEFAULT 0;

-- Add index for display_order if it doesn't exist
CREATE INDEX IF NOT EXISTS idx_stories_display_order ON stories (display_order);

-- ============================================================================
-- SEED DEFAULT DATA
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Insert default homepage settings
-- ----------------------------------------------------------------------------
INSERT INTO homepage_settings (
  hero_title,
  hero_tagline,
  hero_description,
  brand_color,
  newsletter_cta_text,
  show_featured_story,
  show_activity_feed,
  show_tools_section
) VALUES (
  'Step into the worlds of',
  'Shared Multiverse Portal',
  'Starships, sky-pirates, cursed knights, and reluctant warlocks. One site to explore every series, follow new chapters, and get early access to the stories before they go live.',
  '#10b981',
  'Join the Newsletter',
  true,
  true,
  true
);

-- ----------------------------------------------------------------------------
-- Seed default tools
-- ----------------------------------------------------------------------------
INSERT INTO homepage_tools (title, description, icon, link, display_order, is_active) VALUES
('Browse All Stories', 'Explore the complete library of stories, universes, and series.', '📚', '/storytime', 1, true),
('LitRPG Tools', 'Create, track, and balance characters using the same system behind the books.', '📊', '/litrpg', 2, true),
('Image Galleries', 'Concept art, character portraits, ship designs, and location mood boards.', '🖼️', '/galleries', 3, true),
('Shoutout Manager', 'Automated shoutout calendar for RoyalRoad swaps and cross-promo.', '📢', '/shoutouts', 4, true);

-- ----------------------------------------------------------------------------
-- Seed sample activity feed items (optional - can be removed)
-- ----------------------------------------------------------------------------
INSERT INTO activity_feed (type, source, label, title, series_title, url, published_at, is_active) VALUES
('announcement', 'Site', 'Announcement', 'New Homepage Launched!', 'Site Updates', '/homepage-v2', NOW(), true),
('misc', 'Blog', 'Dev Log', 'Building the Universe Portal', 'Site Blog', '#', DATE_SUB(NOW(), INTERVAL 2 DAY), true);

-- ============================================================================
-- VERIFICATION QUERIES (Comment out after running migration)
-- ============================================================================

-- Check that all tables exist
-- SELECT TABLE_NAME FROM information_schema.TABLES
-- WHERE TABLE_SCHEMA = DATABASE()
-- AND TABLE_NAME IN ('homepage_settings', 'homepage_tools', 'activity_feed');

-- Check that stories columns were added
-- DESCRIBE stories;

-- Check seeded data
-- SELECT * FROM homepage_settings;
-- SELECT * FROM homepage_tools;
-- SELECT * FROM activity_feed;

-- ============================================================================
-- ROLLBACK INSTRUCTIONS (if needed)
-- ============================================================================

-- DROP TABLE IF EXISTS homepage_settings;
-- DROP TABLE IF EXISTS homepage_tools;
-- DROP TABLE IF EXISTS activity_feed;

-- ALTER TABLE stories
--   DROP COLUMN IF EXISTS continue_url_royalroad,
--   DROP COLUMN IF EXISTS continue_url_patreon,
--   DROP COLUMN IF EXISTS latest_chapter_number,
--   DROP COLUMN IF EXISTS latest_chapter_title,
--   DROP COLUMN IF EXISTS teaser_chapter_count,
--   DROP COLUMN IF EXISTS genre_tags,
--   DROP COLUMN IF EXISTS cta_text,
--   DROP COLUMN IF EXISTS is_featured,
--   DROP COLUMN IF EXISTS display_order;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
