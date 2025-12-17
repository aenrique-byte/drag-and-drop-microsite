-- Migration: Add unspent_attribute_points column to litrpg_characters
-- Run this SQL on your database to add the new column

ALTER TABLE `litrpg_characters` 
ADD COLUMN `unspent_attribute_points` int(11) NOT NULL DEFAULT 0 
COMMENT 'Pool of unspent attribute points from leveling' 
AFTER `base_stats`;

-- Notes:
-- - Existing characters will have 0 unspent points (all current attributes are considered "banked")
-- - When leveling up, add 2 (or 4 at higher levels) to this pool
-- - When allocating points, decrement this pool
-- - On save, persist both the attributes and this pool
