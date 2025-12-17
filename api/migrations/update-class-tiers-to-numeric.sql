-- Migration: Update class tiers from named to numeric
-- Date: 2024-12-01
-- Description: Changes tier column from ENUM(base,advanced,elite,legendary,mythic,transcendent) 
--              to VARCHAR(10) with values tier-1 through tier-6

-- Step 1: Change column type from ENUM to VARCHAR (allows any value temporarily)
ALTER TABLE litrpg_classes 
MODIFY COLUMN tier VARCHAR(20) DEFAULT 'tier-1';

-- Step 2: Update existing tier values to numeric format
UPDATE litrpg_classes SET tier = 'tier-1' WHERE tier = 'base';
UPDATE litrpg_classes SET tier = 'tier-2' WHERE tier = 'advanced';
UPDATE litrpg_classes SET tier = 'tier-3' WHERE tier = 'elite';
UPDATE litrpg_classes SET tier = 'tier-4' WHERE tier = 'legendary';
UPDATE litrpg_classes SET tier = 'tier-5' WHERE tier = 'mythic';
UPDATE litrpg_classes SET tier = 'tier-6' WHERE tier = 'transcendent';

-- Step 3: Optional - Change to ENUM for validation (uncomment if preferred)
-- ALTER TABLE litrpg_classes 
-- MODIFY COLUMN tier ENUM('tier-1','tier-2','tier-3','tier-4','tier-5','tier-6') DEFAULT 'tier-1';
