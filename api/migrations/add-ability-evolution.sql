-- Add ability evolution column
-- Run this to add support for ability evolution chains
-- NOTE: The column may already exist from the initial schema. Run each statement individually if needed.

-- Only run this if the column doesn't already exist:
-- Check first: SHOW COLUMNS FROM litrpg_abilities LIKE 'evolution_ability_id';

-- If column doesn't exist, add it:
ALTER TABLE litrpg_abilities 
ADD COLUMN IF NOT EXISTS evolution_ability_id INT UNSIGNED NULL AFTER max_level;

-- Add foreign key constraint (only if not already exists)
-- You may need to drop and recreate if it already exists with a different name
-- ALTER TABLE litrpg_abilities ADD CONSTRAINT fk_ability_evolution FOREIGN KEY (evolution_ability_id) REFERENCES litrpg_abilities(id) ON DELETE SET NULL;

-- Add index for faster lookups (only if not exists)
-- CREATE INDEX IF NOT EXISTS idx_ability_evolution ON litrpg_abilities(evolution_ability_id);

-- Note: The create.php API already supports evolution_ability_id
-- The API will save/retrieve this field automatically
