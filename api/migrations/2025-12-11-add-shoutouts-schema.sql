-- ============================================
-- Shoutouts Feature Migration
-- Date: 2025-12-11
-- ============================================
-- This migration adds the shoutouts feature tables and configuration

-- ============================================
-- STEP 1: Add show_shoutouts flag to author_profile
-- ============================================
ALTER TABLE author_profile 
ADD COLUMN IF NOT EXISTS show_shoutouts BOOLEAN DEFAULT FALSE 
COMMENT 'Toggle to show/hide shoutouts button on homepage';

-- ============================================
-- STEP 2: Email Configuration Table
-- ============================================
-- Centralized email/SMTP settings for shoutouts, blog, and future features
CREATE TABLE IF NOT EXISTS email_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    description VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default email configuration (empty - to be filled via admin panel)
INSERT INTO email_config (config_key, config_value, description) VALUES 
('smtp_host', '', 'SMTP server hostname (e.g., smtp.hostinger.com)'),
('smtp_port', '465', 'SMTP port (465 for SSL, 587 for TLS)'),
('smtp_user', '', 'SMTP username (usually your email address)'),
('smtp_pass', '', 'SMTP password (encrypted in production)'),
('smtp_encryption', 'ssl', 'Encryption method: ssl or tls'),
('from_email', '', 'From email address (must be valid mailbox on your domain)'),
('from_name', 'Author Website', 'Display name for outgoing emails'),
('admin_email', '', 'Admin email for notifications')
ON DUPLICATE KEY UPDATE config_value = config_value;

-- ============================================
-- STEP 3: Shoutout Configuration Table
-- ============================================
CREATE TABLE IF NOT EXISTS shoutout_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default config
INSERT INTO shoutout_config (config_key, config_value) VALUES 
('monthsToShow', '3')
ON DUPLICATE KEY UPDATE config_value = config_value;

-- ============================================
-- STEP 4: Shoutout Stories Table
-- ============================================
CREATE TABLE IF NOT EXISTS shoutout_stories (
    id VARCHAR(50) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    link VARCHAR(500) NOT NULL,
    cover_image VARCHAR(500) NOT NULL,
    color ENUM('amber', 'blue', 'rose', 'emerald', 'violet', 'cyan') DEFAULT 'amber',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_title (title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default story (required for app to work - user will edit this)
INSERT INTO shoutout_stories (id, title, link, cover_image, color) VALUES 
('default', 'My Royal Road Story', 'https://www.royalroad.com/fiction/12345/my-story', 'https://picsum.photos/400/600', 'amber')
ON DUPLICATE KEY UPDATE title = title;

-- ============================================
-- STEP 5: Shoutout Admin Shoutouts (Templates)
-- ============================================
CREATE TABLE IF NOT EXISTS shoutout_admin_shoutouts (
    id VARCHAR(50) PRIMARY KEY,
    label VARCHAR(255) NOT NULL,
    code TEXT NOT NULL,
    story_id VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (story_id) REFERENCES shoutout_stories(id) ON DELETE CASCADE,
    INDEX idx_story_id (story_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default shoutout template (user will edit this)
INSERT INTO shoutout_admin_shoutouts (id, label, code, story_id) VALUES 
('1', 'Main Shoutout', '<div style="border: 1px solid #ccc; padding: 10px;"><strong>My Story</strong><br><a href="#">Read Now</a></div>', 'default')
ON DUPLICATE KEY UPDATE label = label;

-- ============================================
-- STEP 6: Shoutout Availability
-- ============================================
CREATE TABLE IF NOT EXISTS shoutout_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_str DATE NOT NULL,
    story_id VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (story_id) REFERENCES shoutout_stories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_date_story (date_str, story_id),
    INDEX idx_date_str (date_str),
    INDEX idx_story_id (story_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- STEP 7: Shoutout Bookings
-- ============================================
CREATE TABLE IF NOT EXISTS shoutout_bookings (
    id VARCHAR(50) PRIMARY KEY,
    date_str DATE NOT NULL,
    story_id VARCHAR(50) NOT NULL,
    author_name VARCHAR(255) NOT NULL,
    story_link VARCHAR(500) NOT NULL,
    shoutout_code TEXT NOT NULL,
    email VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (story_id) REFERENCES shoutout_stories(id) ON DELETE CASCADE,
    INDEX idx_date_str (date_str),
    INDEX idx_story_id (story_id),
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SUMMARY OF CHANGES
-- ============================================
-- 
-- 1. author_profile.show_shoutouts - Feature toggle flag
-- 2. email_config - Centralized SMTP/email settings (admin editable)
-- 3. shoutout_config - Shoutout app configuration
-- 4. shoutout_stories - Royal Road stories/books
-- 5. shoutout_admin_shoutouts - Shoutout templates/codes
-- 6. shoutout_availability - Available booking dates
-- 7. shoutout_bookings - Author booking requests
--
-- No conflicts with existing schema - all tables prefixed with 'shoutout_'
-- Email config shared across all features (blog, shoutouts, etc.)
-- ============================================
