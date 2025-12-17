-- =====================================================
-- ADD PRIMARY AND SECONDARY ATTRIBUTES TO CLASSES
-- Required for combat math calculations
-- =====================================================

-- Add columns to litrpg_classes table
ALTER TABLE litrpg_classes
ADD COLUMN primary_attribute VARCHAR(3) DEFAULT NULL COMMENT 'Primary stat: STR, PER, DEX, MEM, INT, CHA',
ADD COLUMN secondary_attribute VARCHAR(3) DEFAULT NULL COMMENT 'Secondary stat: STR, PER, DEX, MEM, INT, CHA';

-- Update existing classes with sensible defaults
UPDATE litrpg_classes SET primary_attribute = 'STR', secondary_attribute = 'PER' WHERE slug = 'recruit';
UPDATE litrpg_classes SET primary_attribute = 'STR', secondary_attribute = 'DEX' WHERE slug = 'soldier';
UPDATE litrpg_classes SET primary_attribute = 'INT', secondary_attribute = 'MEM' WHERE slug = 'engineer';
UPDATE litrpg_classes SET primary_attribute = 'INT', secondary_attribute = 'CHA' WHERE slug = 'medic';
UPDATE litrpg_classes SET primary_attribute = 'STR', secondary_attribute = 'DEX' WHERE slug = 'commando';
UPDATE litrpg_classes SET primary_attribute = 'PER', secondary_attribute = 'DEX' WHERE slug = 'specialist';
UPDATE litrpg_classes SET primary_attribute = 'INT', secondary_attribute = 'DEX' WHERE slug = 'hacker';
UPDATE litrpg_classes SET primary_attribute = 'MEM', secondary_attribute = 'INT' WHERE slug = 'psi-ops';

-- Verification query
-- SELECT slug, name, primary_attribute, secondary_attribute FROM litrpg_classes;
