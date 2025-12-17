-- =====================================================
-- LitRPG Schema Update - December 2025
-- Migration to Constants-Based System with Professions
-- =====================================================
-- 
-- SUMMARY OF CHANGES:
-- 1. Drop obsolete tables (classes, abilities, items, monsters, contracts now in constants)
-- 2. Update litrpg_characters table for new character sheet features:
--    - Add profession support (profession_name, profession_activated_at_level)
--    - Add class history tracking for stat bonuses (class_history_with_levels)
--    - Add profession history tracking (profession_history_with_levels)
--    - Update abilities structure from array to object with levels
--    - Add tier progression tracking (highest_tier_achieved)
--    - Add character customization fields
--    - Remove obsolete fields (class_level, neural_heat)
-- 
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Disable foreign key checks to allow dropping tables with constraints
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- PHASE 1: DROP OBSOLETE TABLES
-- =====================================================
-- These tables are now managed via TypeScript constants files
-- All data has been migrated to frontend constants

-- Drop in correct order: child tables before parent tables
DROP TABLE IF EXISTS `litrpg_ability_tiers`;
DROP TABLE IF EXISTS `litrpg_abilities`;
DROP TABLE IF EXISTS `litrpg_contracts`;
DROP TABLE IF EXISTS `litrpg_monsters`;
DROP TABLE IF EXISTS `litrpg_items`;
DROP TABLE IF EXISTS `litrpg_classes`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- PHASE 2: BACKUP EXISTING CHARACTER DATA
-- =====================================================
-- Create temporary backup table before migration

DROP TABLE IF EXISTS `litrpg_characters_backup_20251201`;

CREATE TABLE `litrpg_characters_backup_20251201` AS 
SELECT * FROM `litrpg_characters`;

-- =====================================================
-- PHASE 3: UPDATE litrpg_characters TABLE STRUCTURE
-- =====================================================

-- Add new profession columns
ALTER TABLE `litrpg_characters`
ADD COLUMN `profession_name` VARCHAR(100) DEFAULT NULL COMMENT 'Current profession name (from profession constants)' AFTER `class_id`,
ADD COLUMN `profession_activated_at_level` INT UNSIGNED DEFAULT NULL COMMENT 'Level when current profession was activated' AFTER `profession_name`;

-- Add class/profession history tracking columns
ALTER TABLE `litrpg_characters`
ADD COLUMN `class_activated_at_level` INT UNSIGNED DEFAULT 1 COMMENT 'Level when current class was activated' AFTER `class_id`,
ADD COLUMN `class_history` JSON DEFAULT NULL COMMENT 'Array of previous class names for ability retention' AFTER `class_activated_at_level`,
ADD COLUMN `class_history_with_levels` JSON DEFAULT NULL COMMENT 'Detailed history: [{className, activatedAtLevel, deactivatedAtLevel}]' AFTER `class_history`,
ADD COLUMN `profession_history_with_levels` JSON DEFAULT NULL COMMENT 'Profession history: [{className, activatedAtLevel, deactivatedAtLevel}]' AFTER `profession_activated_at_level`;

-- Add tier progression tracking
ALTER TABLE `litrpg_characters`
ADD COLUMN `highest_tier_achieved` INT UNSIGNED DEFAULT 1 COMMENT 'Highest class tier number achieved (for ability point bonuses)' AFTER `class_history_with_levels`;

-- Add character customization fields
ALTER TABLE `litrpg_characters`
ADD COLUMN `header_image_url` VARCHAR(255) DEFAULT NULL COMMENT 'Banner image URL for character sheet' AFTER `portrait_image`;

-- Modify class_id to be nullable (class name is primary identifier now)
ALTER TABLE `litrpg_characters`
MODIFY COLUMN `class_id` INT UNSIGNED DEFAULT NULL COMMENT 'Legacy field - class identified by name now';

-- Remove obsolete columns
ALTER TABLE `litrpg_characters`
DROP COLUMN IF EXISTS `class_level`,
DROP COLUMN IF EXISTS `neural_heat`;

-- Rename stats to attributes if needed (optional - can keep as stats)
-- ALTER TABLE `litrpg_characters`
-- CHANGE COLUMN `stats` `attributes` JSON DEFAULT NULL COMMENT 'Character attributes: {STR, DEX, PER, MEM, INT, CHA}';

-- Update unlocked_abilities comment to reflect new structure
ALTER TABLE `litrpg_characters`
MODIFY COLUMN `unlocked_abilities` JSON DEFAULT NULL COMMENT 'Abilities with levels: {"ability_name": level}';

-- Update equipped_items comment
ALTER TABLE `litrpg_characters`
MODIFY COLUMN `equipped_items` JSON DEFAULT NULL COMMENT 'Equipped items: {armor, weapon_primary, weapon_secondary, accessory_1, accessory_2, accessory_3}';

-- Add history field if not exists
ALTER TABLE `litrpg_characters`
ADD COLUMN IF NOT EXISTS `history` JSON DEFAULT NULL COMMENT 'Character event log (adventure log)' AFTER `unlocked_abilities`;

-- =====================================================
-- PHASE 4: DATA MIGRATION
-- =====================================================

-- Initialize new fields for existing characters
UPDATE `litrpg_characters`
SET 
    `class_activated_at_level` = 1,
    `highest_tier_achieved` = 1,
    `class_history` = JSON_ARRAY(),
    `class_history_with_levels` = JSON_ARRAY(),
    `profession_history_with_levels` = JSON_ARRAY(),
    `history` = JSON_ARRAY()
WHERE `class_activated_at_level` IS NULL;

-- Convert unlocked_abilities from array to object structure if needed
-- This requires custom logic based on your current data structure
-- Example: Convert ["ability1", "ability2"] to {"ability1": 1, "ability2": 1}
UPDATE `litrpg_characters`
SET `unlocked_abilities` = '{}'
WHERE `unlocked_abilities` IS NULL OR `unlocked_abilities` = 'null';

-- =====================================================
-- PHASE 5: UPDATE INDEXES
-- =====================================================

-- Add indexes for new columns
CREATE INDEX `idx_profession_name` ON `litrpg_characters` (`profession_name`);
CREATE INDEX `idx_highest_tier` ON `litrpg_characters` (`highest_tier_achieved`);

-- Update existing index on class_id (now optional)
DROP INDEX IF EXISTS `fk_characters_class` ON `litrpg_characters`;
CREATE INDEX `idx_class_id` ON `litrpg_characters` (`class_id`);

-- =====================================================
-- PHASE 6: CREATE NEW SIMPLIFIED SCHEMA
-- =====================================================

-- Create clean litrpg_characters table structure (for reference)
-- This shows the final desired structure

/*
CREATE TABLE `litrpg_characters` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `level` INT UNSIGNED NOT NULL DEFAULT 1,
  `xp_current` INT UNSIGNED NOT NULL DEFAULT 0,
  `xp_to_level` INT UNSIGNED NOT NULL DEFAULT 100,
  
  -- Class System
  `class_id` INT UNSIGNED DEFAULT NULL COMMENT 'Legacy field - class identified by name now',
  `class_activated_at_level` INT UNSIGNED DEFAULT 1 COMMENT 'Level when current class was activated',
  `class_history` JSON DEFAULT NULL COMMENT 'Array of previous class names for ability retention',
  `class_history_with_levels` JSON DEFAULT NULL COMMENT 'Detailed history: [{className, activatedAtLevel, deactivatedAtLevel}]',
  `highest_tier_achieved` INT UNSIGNED DEFAULT 1 COMMENT 'Highest class tier achieved (for ability point bonuses)',
  
  -- Profession System
  `profession_name` VARCHAR(100) DEFAULT NULL COMMENT 'Current profession name (from profession constants)',
  `profession_activated_at_level` INT UNSIGNED DEFAULT NULL COMMENT 'Level when current profession was activated',
  `profession_history_with_levels` JSON DEFAULT NULL COMMENT 'Profession history: [{className, activatedAtLevel, deactivatedAtLevel}]',
  
  -- Attributes & Combat Stats
  `stats` JSON DEFAULT NULL COMMENT 'Character attributes: {STR, DEX, PER, MEM, INT, CHA}',
  `hp_max` INT UNSIGNED NOT NULL DEFAULT 100,
  `hp_current` INT UNSIGNED NOT NULL DEFAULT 100,
  `ep_max` INT UNSIGNED NOT NULL DEFAULT 50,
  `ep_current` INT UNSIGNED NOT NULL DEFAULT 50,
  
  -- Economy
  `credits` INT UNSIGNED NOT NULL DEFAULT 0,
  
  -- Equipment & Inventory
  `equipped_items` JSON DEFAULT NULL COMMENT 'Equipped items: {armor, weapon_primary, weapon_secondary, accessory_1, accessory_2, accessory_3}',
  `inventory` JSON DEFAULT NULL COMMENT 'Array of item IDs',
  
  -- Abilities
  `unlocked_abilities` JSON DEFAULT NULL COMMENT 'Abilities with levels: {"ability_name": level}',
  `history` JSON DEFAULT NULL COMMENT 'Character event log (adventure log)',
  
  -- Media
  `portrait_image` VARCHAR(255) DEFAULT NULL,
  `header_image_url` VARCHAR(255) DEFAULT NULL COMMENT 'Banner image URL for character sheet',
  
  -- Meta
  `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_profession_name` (`profession_name`),
  KEY `idx_highest_tier` (`highest_tier_achieved`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='LitRPG characters - constants-based system with professions';
*/

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Check table structure
-- DESC litrpg_characters;

-- Verify character data
-- SELECT id, slug, name, level, profession_name, highest_tier_achieved FROM litrpg_characters;

-- Check JSON fields
-- SELECT id, name, class_history, profession_name, unlocked_abilities FROM litrpg_characters;

COMMIT;

-- =====================================================
-- ROLLBACK PROCEDURE (if needed)
-- =====================================================
/*
-- To rollback this migration:

START TRANSACTION;

-- Drop the migrated table
DROP TABLE IF EXISTS `litrpg_characters`;

-- Restore from backup
CREATE TABLE `litrpg_characters` AS 
SELECT * FROM `litrpg_characters_backup_20251201`;

-- Restore primary key and indexes
ALTER TABLE `litrpg_characters`
ADD PRIMARY KEY (`id`),
ADD UNIQUE KEY `slug` (`slug`),
ADD KEY `idx_status` (`status`);

COMMIT;
*/

-- =====================================================
-- POST-MIGRATION CLEANUP (run after verification)
-- =====================================================
/*
-- After verifying the migration is successful, you can drop the backup:
-- DROP TABLE IF EXISTS `litrpg_characters_backup_20251201`;
*/

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================
-- Next steps:
-- 1. Run this migration on your database
-- 2. Verify character data is intact
-- 3. Test character creation/updates through the API
-- 4. Update any remaining API endpoints to use new structure
-- 5. Drop backup table after confirmation
-- =====================================================
