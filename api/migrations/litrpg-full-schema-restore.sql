-- =====================================================
-- LitRPG Full Schema - Complete MySQL Restoration
-- =====================================================
-- This schema restores ALL LitRPG tables for full database management
-- Designed to be populated from TypeScript constants via seed scripts
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =====================================================
-- TABLE: litrpg_classes
-- Combat classes (Recruit, Scout, Hunter, etc.)
-- =====================================================
CREATE TABLE IF NOT EXISTS `litrpg_classes` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `tier` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Numeric tier: 1, 2, 3, 4',
  `unlock_level` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Character level required to unlock',
  `prerequisite_class_id` INT UNSIGNED DEFAULT NULL COMMENT 'Required class before unlocking this one',
  `stat_bonuses` JSON DEFAULT NULL COMMENT 'Stat bonuses: {STR: 1, DEX: 2, ...}',
  `primary_attribute` VARCHAR(10) DEFAULT NULL COMMENT 'Primary stat (STR, DEX, PER, etc.)',
  `secondary_attribute` VARCHAR(10) DEFAULT NULL COMMENT 'Secondary stat',
  `starting_item` VARCHAR(255) DEFAULT NULL COMMENT 'Item given when class is first selected',
  `ability_ids` JSON DEFAULT NULL COMMENT 'Array of ability IDs unlocked by this class',
  `upgrade_ids` JSON DEFAULT NULL COMMENT 'Array of class IDs this can upgrade to',
  `icon_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_tier` (`tier`),
  KEY `idx_unlock_level` (`unlock_level`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `fk_prerequisite_class` (`prerequisite_class_id`),
  CONSTRAINT `fk_class_prerequisite` FOREIGN KEY (`prerequisite_class_id`) REFERENCES `litrpg_classes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='LitRPG combat classes';

-- =====================================================
-- TABLE: litrpg_professions
-- Non-combat professions (Pilot, Medical Officer, etc.)
-- =====================================================
CREATE TABLE IF NOT EXISTS `litrpg_professions` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `tier` VARCHAR(20) NOT NULL DEFAULT 'tier-1' COMMENT 'tier-1 or tier-2',
  `unlock_level` INT UNSIGNED NOT NULL DEFAULT 16 COMMENT 'Character level required',
  `prerequisite_profession_id` INT UNSIGNED DEFAULT NULL COMMENT 'Required profession before unlocking',
  `stat_bonuses` JSON DEFAULT NULL COMMENT 'Stat bonuses: {INT: 1, CHA: 1, ...}',
  `ability_ids` JSON DEFAULT NULL COMMENT 'Array of professional ability IDs',
  `icon_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_tier` (`tier`),
  KEY `idx_unlock_level` (`unlock_level`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `fk_prerequisite_profession` (`prerequisite_profession_id`),
  CONSTRAINT `fk_profession_prerequisite` FOREIGN KEY (`prerequisite_profession_id`) REFERENCES `litrpg_professions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='LitRPG professions (non-combat specializations)';

-- =====================================================
-- TABLE: litrpg_abilities
-- Combat abilities (stealth, offense, defense, etc.)
-- =====================================================
CREATE TABLE IF NOT EXISTS `litrpg_abilities` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `max_level` INT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'Maximum ability level',
  `evolution_ability_id` INT UNSIGNED DEFAULT NULL COMMENT 'Ability this evolves into',
  `evolution_level` INT UNSIGNED DEFAULT NULL COMMENT 'Level required for evolution',
  `category` VARCHAR(50) DEFAULT NULL COMMENT 'perception-targeting, offense, defense, etc.',
  `icon_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `fk_evolution_ability` (`evolution_ability_id`),
  CONSTRAINT `fk_ability_evolution` FOREIGN KEY (`evolution_ability_id`) REFERENCES `litrpg_abilities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='LitRPG combat abilities';

-- =====================================================
-- TABLE: litrpg_ability_tiers
-- Ability progression levels (1-10) with effects
-- =====================================================
CREATE TABLE IF NOT EXISTS `litrpg_ability_tiers` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ability_id` INT(10) UNSIGNED NOT NULL,
  `tier_level` INT UNSIGNED NOT NULL COMMENT 'Tier 1-10',
  `duration` VARCHAR(50) DEFAULT NULL COMMENT 'e.g., "6 sec", "15 sec"',
  `cooldown` VARCHAR(50) DEFAULT NULL COMMENT 'e.g., "2 min", "30 sec"',
  `energy_cost` INT UNSIGNED DEFAULT NULL COMMENT 'Energy points required',
  `effect_description` TEXT DEFAULT NULL COMMENT 'Description of effects at this tier',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ability_tier` (`ability_id`, `tier_level`),
  KEY `idx_tier_level` (`tier_level`),
  CONSTRAINT `fk_tier_ability` FOREIGN KEY (`ability_id`) REFERENCES `litrpg_abilities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Ability tier progression details';

-- =====================================================
-- TABLE: litrpg_professional_abilities
-- Profession-specific abilities (piloting, medical, etc.)
-- =====================================================
CREATE TABLE IF NOT EXISTS `litrpg_professional_abilities` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `max_level` INT UNSIGNED NOT NULL DEFAULT 10,
  `category` VARCHAR(50) DEFAULT NULL COMMENT 'piloting, medical, engineering, etc.',
  `icon_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Professional abilities for non-combat professions';

-- =====================================================
-- TABLE: litrpg_professional_ability_tiers
-- Progression tiers for professional abilities
-- =====================================================
CREATE TABLE IF NOT EXISTS `litrpg_professional_ability_tiers` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ability_id` INT(10) UNSIGNED NOT NULL,
  `tier_level` INT UNSIGNED NOT NULL COMMENT 'Tier 1-10',
  `duration` VARCHAR(50) DEFAULT NULL,
  `cooldown` VARCHAR(50) DEFAULT NULL,
  `energy_cost` INT UNSIGNED DEFAULT NULL,
  `effect_description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_prof_ability_tier` (`ability_id`, `tier_level`),
  KEY `idx_tier_level` (`tier_level`),
  CONSTRAINT `fk_prof_tier_ability` FOREIGN KEY (`ability_id`) REFERENCES `litrpg_professional_abilities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Professional ability tier progression';

-- =====================================================
-- TABLE: litrpg_items
-- All items/loot (weapons, armor, materials, consumables)
-- =====================================================
CREATE TABLE IF NOT EXISTS `litrpg_items` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `tech_level` VARCHAR(10) DEFAULT NULL COMMENT 'TL8, TL9, TL10',
  `category` VARCHAR(50) DEFAULT NULL COMMENT 'Weapon, Armor, Tool, Material, Consumable, Medical',
  `rarity` VARCHAR(20) DEFAULT 'common' COMMENT 'common, uncommon, rare, legendary',
  `base_value` INT UNSIGNED DEFAULT 0 COMMENT 'Base credit value',
  `stats` JSON DEFAULT NULL COMMENT 'Item stats/bonuses',
  `requirements` JSON DEFAULT NULL COMMENT 'Level/class requirements',
  `icon_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_category` (`category`),
  KEY `idx_tech_level` (`tech_level`),
  KEY `idx_rarity` (`rarity`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='LitRPG items and loot';

-- =====================================================
-- TABLE: litrpg_monsters
-- All monsters/enemies
-- =====================================================
CREATE TABLE IF NOT EXISTS `litrpg_monsters` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `level` INT UNSIGNED NOT NULL DEFAULT 1,
  `rank` ENUM('Trash','Regular','Champion','Boss') NOT NULL DEFAULT 'Regular',
  `hp` INT UNSIGNED DEFAULT NULL COMMENT 'Hit points',
  `xp_reward` INT UNSIGNED NOT NULL DEFAULT 0,
  `credits` INT UNSIGNED NOT NULL DEFAULT 0,
  `stats` JSON DEFAULT NULL COMMENT 'Monster stats: {STR: 1, PER: 1, ...}',
  `abilities` JSON DEFAULT NULL COMMENT 'Array of ability names',
  `loot_table` JSON DEFAULT NULL COMMENT 'Array of {item: "name", rate: 0.5}',
  `icon_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_level` (`level`),
  KEY `idx_rank` (`rank`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='LitRPG monsters and enemies';

-- =====================================================
-- TABLE: litrpg_contracts
-- Quests and missions
-- =====================================================
CREATE TABLE IF NOT EXISTS `litrpg_contracts` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `contract_type` VARCHAR(50) DEFAULT NULL COMMENT 'bounty, extraction, escort, patrol, investigation',
  `difficulty` VARCHAR(20) DEFAULT 'routine' COMMENT 'routine, hazardous, critical, suicide',
  `level_requirement` INT UNSIGNED NOT NULL DEFAULT 1,
  `time_limit` VARCHAR(50) DEFAULT NULL COMMENT 'e.g., "4 hours", "2 days"',
  `objectives` JSON DEFAULT NULL COMMENT 'Array of {description, target, current}',
  `rewards` JSON DEFAULT NULL COMMENT '{xp: 500, credits: 250, items: [...]}',
  `icon_image` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_difficulty` (`difficulty`),
  KEY `idx_level_requirement` (`level_requirement`),
  KEY `idx_contract_type` (`contract_type`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='LitRPG contracts and quests';

-- =====================================================
-- TABLE: litrpg_characters (Already exists, just ensure it's correct)
-- Player characters
-- =====================================================
CREATE TABLE IF NOT EXISTS `litrpg_characters` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(100) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `level` INT UNSIGNED NOT NULL DEFAULT 1,
  `xp_current` INT UNSIGNED NOT NULL DEFAULT 0,
  `xp_to_level` INT UNSIGNED NOT NULL DEFAULT 100,

  -- Class System
  `class_id` INT UNSIGNED DEFAULT NULL COMMENT 'Current combat class',
  `class_activated_at_level` INT UNSIGNED DEFAULT 1 COMMENT 'Level when current class was activated',
  `class_history` JSON DEFAULT NULL COMMENT 'Array of previous class names',
  `class_history_with_levels` JSON DEFAULT NULL COMMENT '[{className, activatedAtLevel, deactivatedAtLevel}]',
  `highest_tier_achieved` INT UNSIGNED DEFAULT 1 COMMENT 'Highest tier reached',

  -- Profession System
  `profession_id` INT UNSIGNED DEFAULT NULL COMMENT 'Current profession',
  `profession_activated_at_level` INT UNSIGNED DEFAULT NULL,
  `profession_history` JSON DEFAULT NULL COMMENT 'Array of previous profession names',
  `profession_history_with_levels` JSON DEFAULT NULL COMMENT '[{professionName, activatedAtLevel, deactivatedAtLevel}]',

  -- Attributes & Combat Stats
  `stats` JSON DEFAULT NULL COMMENT '{STR, DEX, PER, MEM, INT, CHA}',
  `hp_max` INT UNSIGNED NOT NULL DEFAULT 100,
  `hp_current` INT UNSIGNED NOT NULL DEFAULT 100,
  `ep_max` INT UNSIGNED NOT NULL DEFAULT 50,
  `ep_current` INT UNSIGNED NOT NULL DEFAULT 50,

  -- Economy
  `credits` INT UNSIGNED NOT NULL DEFAULT 0,

  -- Equipment & Inventory
  `equipped_items` JSON DEFAULT NULL COMMENT '{armor, weapon_primary, weapon_secondary, accessory_1, accessory_2, accessory_3}',
  `inventory` JSON DEFAULT NULL COMMENT 'Array of item IDs',

  -- Abilities
  `unlocked_abilities` JSON DEFAULT NULL COMMENT '{"ability_id": level} - both combat and professional',
  `history` JSON DEFAULT NULL COMMENT 'Character event log',

  -- Media
  `portrait_image` VARCHAR(255) DEFAULT NULL,
  `header_image_url` VARCHAR(255) DEFAULT NULL,

  -- Meta
  `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_level` (`level`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `fk_class` (`class_id`),
  KEY `fk_profession` (`profession_id`),
  CONSTRAINT `fk_character_class` FOREIGN KEY (`class_id`) REFERENCES `litrpg_classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_character_profession` FOREIGN KEY (`profession_id`) REFERENCES `litrpg_professions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='LitRPG player characters';

COMMIT;

-- =====================================================
-- SUMMARY OF TABLES
-- =====================================================
-- litrpg_classes                      - Combat classes
-- litrpg_professions                  - Non-combat professions
-- litrpg_abilities                    - Combat abilities
-- litrpg_ability_tiers               - Combat ability progression
-- litrpg_professional_abilities      - Professional abilities
-- litrpg_professional_ability_tiers  - Professional ability progression
-- litrpg_items                       - All items/loot
-- litrpg_monsters                    - Monsters/enemies
-- litrpg_contracts                   - Quests/contracts
-- litrpg_characters                  - Player characters
-- =====================================================

-- =====================================================
-- FIELD REFERENCE FOR SEED SCRIPTS
-- =====================================================
--
-- CLASSES:
-- id, slug, name, description, tier, unlock_level, prerequisite_class_id,
-- stat_bonuses (JSON), primary_attribute, secondary_attribute, starting_item,
-- ability_ids (JSON array), upgrade_ids (JSON array)
--
-- PROFESSIONS:
-- id, slug, name, description, tier, unlock_level, prerequisite_profession_id,
-- stat_bonuses (JSON), ability_ids (JSON array)
--
-- ABILITIES:
-- id, slug, name, description, max_level, evolution_ability_id, evolution_level, category
--
-- ABILITY_TIERS:
-- ability_id, tier_level, duration, cooldown, energy_cost, effect_description
--
-- PROFESSIONAL_ABILITIES:
-- id, slug, name, description, max_level, category
--
-- PROFESSIONAL_ABILITY_TIERS:
-- ability_id, tier_level, duration, cooldown, energy_cost, effect_description
--
-- ITEMS:
-- id, slug, name, description, tech_level, category, rarity, base_value,
-- stats (JSON), requirements (JSON)
--
-- MONSTERS:
-- id, slug, name, description, level, rank, hp, xp_reward, credits,
-- stats (JSON), abilities (JSON array), loot_table (JSON array)
--
-- CONTRACTS:
-- id, slug, title, description, contract_type, difficulty, level_requirement,
-- time_limit, objectives (JSON array), rewards (JSON)
--
-- =====================================================
