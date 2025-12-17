-- ============================================
-- Quick Fix: Seed Default Shoutouts Data
-- Run this if shoutouts page shows "Cannot read properties of undefined"
-- ============================================

-- Insert default story if none exists
INSERT INTO shoutout_stories (id, title, link, cover_image, color) VALUES 
('default', 'My Royal Road Story', 'https://www.royalroad.com/fiction/12345/my-story', 'https://picsum.photos/400/600', 'amber')
ON DUPLICATE KEY UPDATE title = title;

-- Insert default shoutout template if none exists
INSERT INTO shoutout_admin_shoutouts (id, label, code, story_id) VALUES 
('1', 'Main Shoutout', '<div style="border: 1px solid #ccc; padding: 10px;"><strong>My Story</strong><br><a href="#">Read Now</a></div>', 'default')
ON DUPLICATE KEY UPDATE label = label;

-- Insert default config if none exists
INSERT INTO shoutout_config (config_key, config_value) VALUES 
('monthsToShow', '3')
ON DUPLICATE KEY UPDATE config_value = config_value;

-- Done! Now login to shoutouts admin and edit the default story with your actual info.
