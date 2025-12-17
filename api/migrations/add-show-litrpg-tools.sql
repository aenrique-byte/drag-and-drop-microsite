-- Migration: Add show_litrpg_tools column to author_profile
-- Date: 2024-11-30
-- Description: Adds a toggle to show/hide LitRPG Tools link on homepage

ALTER TABLE author_profile 
ADD COLUMN IF NOT EXISTS show_litrpg_tools TINYINT(1) DEFAULT 1;

-- Update existing rows to have the default value
UPDATE author_profile SET show_litrpg_tools = 1 WHERE show_litrpg_tools IS NULL;
