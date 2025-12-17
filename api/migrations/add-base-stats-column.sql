-- Migration: Add base_stats column to litrpg_characters table
-- This column stores the character's base attribute points (spent by player)
-- separately from bonuses that come from classes and professions

ALTER TABLE litrpg_characters 
ADD COLUMN base_stats JSON NULL AFTER stats;

-- Copy existing stats to base_stats for existing characters
UPDATE litrpg_characters 
SET base_stats = stats 
WHERE base_stats IS NULL AND stats IS NOT NULL;
