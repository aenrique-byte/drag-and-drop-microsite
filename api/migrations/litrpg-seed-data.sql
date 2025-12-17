-- =====================================================
-- LitRPG SEED DATA
-- Sample items for testing the Equipment Section
-- =====================================================

-- Clear existing test data (optional - comment out in production)
-- DELETE FROM litrpg_items WHERE 1=1;

-- =====================================================
-- ARMOR (TL8, TL9, TL10)
-- =====================================================
INSERT INTO litrpg_items (slug, name, description, tech_level, category, stats_bonus, value, sort_order) VALUES
('light-vest', 'Light Combat Vest', 'Basic ballistic protection for vital areas.', 'TL8', 'Armor', '{"STR": 1, "DEX": 0}', 500, 1),
('tactical-jacket', 'Tactical Jacket', 'Reinforced jacket with ceramic plates.', 'TL8', 'Armor', '{"STR": 2}', 1200, 2),
('nano-weave-suit', 'Nano-Weave Suit', 'Advanced polymer weave that hardens on impact.', 'TL9', 'Armor', '{"STR": 2, "DEX": 1}', 3500, 3),
('adaptive-armor', 'Adaptive Combat Armor', 'Self-repairing armor with threat response system.', 'TL9', 'Armor', '{"STR": 3, "PER": 1}', 7500, 4),
('exo-shell', 'Exo-Shell Mk.IV', 'Full-body powered armor with integrated shields.', 'TL10', 'Armor', '{"STR": 5, "DEX": 2, "PER": 1}', 25000, 5);

-- =====================================================
-- WEAPONS (TL8, TL9, TL10)
-- =====================================================
INSERT INTO litrpg_items (slug, name, description, tech_level, category, stats_bonus, value, sort_order) VALUES
('combat-knife', 'Combat Knife', 'Standard issue tactical blade.', 'TL8', 'Weapon', '{"STR": 1, "DEX": 1}', 150, 10),
('pistol-9mm', '9mm Service Pistol', 'Reliable sidearm with moderate stopping power.', 'TL8', 'Weapon', '{"DEX": 2}', 400, 11),
('assault-rifle', 'AR-15 Variant', 'Semi-automatic rifle with modular attachments.', 'TL8', 'Weapon', '{"DEX": 2, "PER": 1}', 1800, 12),
('plasma-cutter', 'Plasma Cutter', 'Industrial tool repurposed for combat.', 'TL9', 'Weapon', '{"STR": 3, "INT": 1}', 4200, 13),
('rail-pistol', 'Rail Pistol', 'Electromagnetic accelerator handgun.', 'TL9', 'Weapon', '{"DEX": 3, "PER": 2}', 6500, 14),
('gauss-rifle', 'Gauss Rifle', 'Magnetically accelerated projectile weapon.', 'TL10', 'Weapon', '{"DEX": 4, "PER": 3, "STR": 1}', 18000, 15),
('neural-blade', 'Neural Blade', 'Monomolecular edge synced to neural interface.', 'TL10', 'Weapon', '{"STR": 4, "DEX": 3, "MEM": 1}', 22000, 16);

-- =====================================================
-- TOOLS
-- =====================================================
INSERT INTO litrpg_items (slug, name, description, tech_level, category, stats_bonus, value, sort_order) VALUES
('multi-tool', 'Multi-Tool', 'Essential field equipment for repairs.', 'TL8', 'Tool', '{"INT": 1}', 100, 20),
('hacking-kit', 'Hacking Kit', 'Portable intrusion suite with bypass modules.', 'TL9', 'Tool', '{"INT": 2, "MEM": 1}', 2500, 21),
('neural-jack', 'Neural Jack', 'Direct interface port for system access.', 'TL10', 'Tool', '{"INT": 3, "MEM": 2}', 8000, 22);

-- =====================================================
-- MEDICAL
-- =====================================================
INSERT INTO litrpg_items (slug, name, description, tech_level, category, stats_bonus, value, sort_order) VALUES
('medkit-basic', 'Basic Medkit', 'Standard first aid supplies.', 'TL8', 'Medical', null, 75, 30),
('stim-pack', 'Stim Pack', 'Combat stimulant for emergency recovery.', 'TL8', 'Medical', '{"STR": 1}', 200, 31),
('nano-injector', 'Nano Injector', 'Programmable nanobots for rapid healing.', 'TL9', 'Medical', '{"STR": 1, "DEX": 1}', 1500, 32),
('regen-module', 'Regeneration Module', 'Implanted healing system.', 'TL10', 'Medical', '{"STR": 2, "DEX": 1, "INT": 1}', 12000, 33);

-- =====================================================
-- ACCESSORIES / COMPONENTS
-- =====================================================
INSERT INTO litrpg_items (slug, name, description, tech_level, category, stats_bonus, value, sort_order) VALUES
('targeting-scope', 'Targeting Scope', 'Enhanced optical sighting system.', 'TL8', 'Component', '{"PER": 2}', 600, 40),
('comms-unit', 'Tactical Comms Unit', 'Encrypted communication device.', 'TL8', 'Component', '{"CHA": 1}', 350, 41),
('reaction-booster', 'Reaction Booster', 'Neural enhancement for faster reflexes.', 'TL9', 'Component', '{"DEX": 2, "PER": 1}', 4000, 42),
('memory-chip', 'Memory Enhancement Chip', 'Cognitive boost implant.', 'TL9', 'Component', '{"MEM": 3, "INT": 1}', 5500, 43),
('stealth-field', 'Personal Stealth Field', 'Light-bending camouflage device.', 'TL10', 'Component', '{"DEX": 3, "PER": 2}', 15000, 44),
('neural-amp', 'Neural Amplifier', 'Brainwave enhancement system.', 'TL10', 'Component', '{"INT": 4, "MEM": 2, "CHA": 1}', 20000, 45);

-- =====================================================
-- CONSUMABLES
-- =====================================================
INSERT INTO litrpg_items (slug, name, description, tech_level, category, stats_bonus, value, sort_order) VALUES
('ration-pack', 'Ration Pack', 'Standard field nutrition.', 'TL8', 'Consumable', null, 25, 50),
('energy-drink', 'Combat Energy Drink', 'Temporary stamina boost.', 'TL8', 'Consumable', '{"DEX": 1}', 50, 51),
('focus-stim', 'Focus Stimulant', 'Enhances concentration temporarily.', 'TL9', 'Consumable', '{"INT": 2, "MEM": 1}', 300, 52);

-- =====================================================
-- SAMPLE CHARACTER (for testing)
-- =====================================================
INSERT INTO litrpg_characters (slug, name, description, level, xp_current, xp_to_level, class_level, stats, hp_max, hp_current, ep_max, ep_current, credits, status)
VALUES (
    'test-operative',
    'Agent Zero',
    'A seasoned field operative with cybernetic enhancements.',
    15,
    12500,
    15000,
    5,
    '{"STR": 12, "PER": 14, "DEX": 16, "MEM": 10, "INT": 13, "CHA": 8}',
    180,
    180,
    75,
    75,
    5000,
    'active'
);

-- =====================================================
-- CLASSES
-- =====================================================
INSERT INTO litrpg_classes (slug, name, description, tier, unlock_level, prerequisite_class_id, stat_bonuses, sort_order) VALUES
('recruit', 'Recruit', 'Fresh conscript with basic training. The foundation for all career paths.', 'base', 1, NULL, '{"STR": 1, "PER": 1, "DEX": 1, "MEM": 1, "INT": 1, "CHA": 1}', 1),
('soldier', 'Soldier', 'Combat specialist focused on direct engagement and tactical warfare.', 'advanced', 66, 1, '{"STR": 3, "PER": 2, "DEX": 2}', 10),
('engineer', 'Engineer', 'Technical expert skilled in systems, hacking, and field repairs.', 'advanced', 66, 1, '{"INT": 3, "MEM": 2, "DEX": 2}', 11),
('medic', 'Medic', 'Field corpsman specializing in combat medicine and support.', 'advanced', 66, 1, '{"INT": 2, "MEM": 3, "CHA": 2}', 12),
('commando', 'Commando', 'Elite assault specialist for high-risk operations.', 'elite', 132, 2, '{"STR": 4, "DEX": 3, "PER": 2}', 20),
('specialist', 'Specialist', 'Precision marksman and tactical coordinator.', 'elite', 132, 2, '{"PER": 4, "DEX": 3, "INT": 2}', 21),
('hacker', 'Hacker', 'Cyber warfare expert capable of infiltrating any system.', 'elite', 132, 3, '{"INT": 4, "MEM": 3, "DEX": 2}', 22),
('psi-ops', 'Psi-Ops', 'Neural enhancement specialist with expanded consciousness.', 'elite', 132, 4, '{"MEM": 4, "INT": 3, "CHA": 2}', 23);

-- =====================================================
-- ABILITIES
-- =====================================================
INSERT INTO litrpg_abilities (slug, name, description, max_level, sort_order) VALUES
('kinetic-strike', 'Kinetic Strike', 'Channel energy into a devastating physical attack that ignores partial armor.', 5, 1),
('neural-boost', 'Neural Boost', 'Temporarily enhance neural pathways for improved reaction time and perception.', 5, 2),
('tactical-analysis', 'Tactical Analysis', 'Analyze enemy patterns to predict attacks and identify weaknesses.', 5, 3),
('field-repair', 'Field Repair', 'Quickly repair equipment and systems in combat conditions.', 5, 4),
('combat-stim', 'Combat Stimulant', 'Inject combat drugs that boost physical performance at a cost.', 5, 5),
('hack-system', 'System Hack', 'Remotely infiltrate and disable electronic systems.', 5, 6),
('emergency-heal', 'Emergency Heal', 'Rapidly stabilize and heal critical wounds using nano-medicine.', 5, 7),
('overwatch', 'Overwatch', 'Enter a defensive stance that automatically engages approaching threats.', 5, 8),
('stealth-cloak', 'Stealth Cloak', 'Activate light-bending technology for temporary invisibility.', 5, 9),
('rage-protocol', 'Rage Protocol', 'Override safety limiters for massive damage increase but take bleedback.', 5, 10);

-- =====================================================
-- ABILITY TIERS
-- =====================================================
-- Kinetic Strike tiers
INSERT INTO litrpg_ability_tiers (ability_id, tier_level, duration, cooldown, energy_cost, effect_description) VALUES
(1, 1, 'Instant', '8 sec', 15, '+50% base damage, 10% armor penetration'),
(1, 2, 'Instant', '7 sec', 18, '+75% base damage, 20% armor penetration'),
(1, 3, 'Instant', '6 sec', 22, '+100% base damage, 30% armor penetration'),
(1, 4, 'Instant', '5 sec', 26, '+125% base damage, 40% armor penetration'),
(1, 5, 'Instant', '4 sec', 30, '+150% base damage, 50% armor penetration, stun on crit');

-- Neural Boost tiers
INSERT INTO litrpg_ability_tiers (ability_id, tier_level, duration, cooldown, energy_cost, effect_description) VALUES
(2, 1, '10 sec', '30 sec', 20, '+10% DEX, +5% dodge chance'),
(2, 2, '12 sec', '28 sec', 24, '+15% DEX, +10% dodge chance'),
(2, 3, '14 sec', '26 sec', 28, '+20% DEX, +15% dodge chance'),
(2, 4, '16 sec', '24 sec', 32, '+25% DEX, +20% dodge chance'),
(2, 5, '20 sec', '20 sec', 36, '+30% DEX, +25% dodge chance, time dilation effect');

-- Tactical Analysis tiers
INSERT INTO litrpg_ability_tiers (ability_id, tier_level, duration, cooldown, energy_cost, effect_description) VALUES
(3, 1, '15 sec', '45 sec', 25, 'Reveal enemy HP, highlight weak points'),
(3, 2, '18 sec', '40 sec', 28, '+5% crit chance vs analyzed targets'),
(3, 3, '21 sec', '35 sec', 31, '+10% crit chance, predict next attack'),
(3, 4, '24 sec', '30 sec', 34, '+15% crit chance, share analysis with team'),
(3, 5, '30 sec', '25 sec', 38, '+20% crit chance, auto-counter telegraphed attacks');

-- Emergency Heal tiers
INSERT INTO litrpg_ability_tiers (ability_id, tier_level, duration, cooldown, energy_cost, effect_description) VALUES
(7, 1, 'Instant', '20 sec', 30, 'Heal 50 HP'),
(7, 2, 'Instant', '18 sec', 35, 'Heal 80 HP'),
(7, 3, 'Instant', '16 sec', 40, 'Heal 120 HP, remove 1 debuff'),
(7, 4, 'Instant', '14 sec', 45, 'Heal 160 HP, remove 2 debuffs'),
(7, 5, 'Instant', '12 sec', 50, 'Heal 200 HP, remove all debuffs, 10 sec regen');

-- =====================================================
-- MONSTERS (Bestiary)
-- =====================================================
-- Trash Mobs
INSERT INTO litrpg_monsters (slug, name, description, level, rank, stats, hp, abilities, loot_table, xp_reward, credits, sort_order) VALUES
('patrol-drone', 'Patrol Drone', 'Automated security drone with basic threat detection. Weak individually but dangerous in swarms.', 5, 'Trash', '{"STR": 6, "PER": 10, "DEX": 8, "MEM": 2, "INT": 2, "CHA": 1}', 40, '["Basic Scan", "Shock Prod"]', '[{"item": "circuit-board", "rate": 0.3}, {"item": "power-cell", "rate": 0.2}]', 25, 10, 1),
('rogue-bot', 'Rogue Maintenance Bot', 'Malfunctioning repair unit with improvised weapons.', 8, 'Trash', '{"STR": 8, "PER": 6, "DEX": 6, "MEM": 4, "INT": 5, "CHA": 1}', 55, '["Welding Torch", "Oil Slick"]', '[{"item": "scrap-metal", "rate": 0.4}, {"item": "multi-tool", "rate": 0.1}]', 35, 15, 2),
('feral-cyborg', 'Feral Cyborg', 'Human whose implants have overridden higher brain functions.', 12, 'Trash', '{"STR": 12, "PER": 8, "DEX": 10, "MEM": 3, "INT": 4, "CHA": 1}', 80, '["Frenzied Slash", "Neural Scream"]', '[{"item": "neural-chip", "rate": 0.15}, {"item": "stim-pack", "rate": 0.25}]', 50, 25, 3);

-- Regular Mobs
INSERT INTO litrpg_monsters (slug, name, description, level, rank, stats, hp, abilities, loot_table, xp_reward, credits, sort_order) VALUES
('corp-security', 'Corporate Security', 'Professional security personnel with standard combat training.', 15, 'Regular', '{"STR": 14, "PER": 12, "DEX": 13, "MEM": 10, "INT": 11, "CHA": 10}', 150, '["Suppressive Fire", "Flashbang", "Take Cover"]', '[{"item": "pistol-9mm", "rate": 0.1}, {"item": "tactical-jacket", "rate": 0.08}]', 120, 75, 10),
('strike-droid', 'Strike Droid', 'Military-grade combat robot with advanced targeting systems.', 20, 'Regular', '{"STR": 18, "PER": 16, "DEX": 14, "MEM": 8, "INT": 12, "CHA": 1}', 220, '["Burst Fire", "Threat Assessment", "Self-Repair"]', '[{"item": "targeting-scope", "rate": 0.12}, {"item": "plasma-cutter", "rate": 0.05}]', 180, 120, 11),
('mutant-stalker', 'Mutant Stalker', 'Radiation-twisted hunter with enhanced senses and regeneration.', 25, 'Regular', '{"STR": 20, "PER": 18, "DEX": 16, "MEM": 6, "INT": 8, "CHA": 4}', 280, '["Pounce", "Regeneration", "Toxic Spit"]', '[{"item": "mutant-tissue", "rate": 0.3}, {"item": "regen-module", "rate": 0.02}]', 250, 150, 12);

-- Champions
INSERT INTO litrpg_monsters (slug, name, description, level, rank, stats, hp, abilities, loot_table, xp_reward, credits, sort_order) VALUES
('heavy-assault-mech', 'Heavy Assault Mech', 'Bipedal war machine armed with rockets and autocannons.', 35, 'Champion', '{"STR": 28, "PER": 20, "DEX": 12, "MEM": 15, "INT": 18, "CHA": 1}', 800, '["Missile Barrage", "Autocannon Sweep", "Shield Generator", "Emergency Protocol"]', '[{"item": "gauss-rifle", "rate": 0.08}, {"item": "exo-shell", "rate": 0.03}]', 800, 500, 20),
('alpha-mutant', 'Alpha Mutant', 'Massive mutated creature that leads packs of lesser mutants.', 40, 'Champion', '{"STR": 32, "PER": 22, "DEX": 18, "MEM": 10, "INT": 14, "CHA": 16}', 1000, '["Devastating Charge", "Pack Command", "Adaptation", "Primal Rage"]', '[{"item": "alpha-gland", "rate": 0.2}, {"item": "neural-amp", "rate": 0.02}]', 1000, 600, 21),
('rogue-ai-node', 'Rogue AI Node', 'Self-aware computing cluster that controls nearby technology.', 45, 'Champion', '{"STR": 10, "PER": 24, "DEX": 16, "MEM": 30, "INT": 32, "CHA": 8}', 650, '["System Override", "Deploy Drones", "Data Storm", "Firewall"]', '[{"item": "memory-chip", "rate": 0.15}, {"item": "hacking-kit", "rate": 0.1}]', 1100, 700, 22);

-- Bosses
INSERT INTO litrpg_monsters (slug, name, description, level, rank, stats, hp, abilities, loot_table, xp_reward, credits, sort_order) VALUES
('sector-overseer', 'Sector Overseer', 'Elite corporate enforcer with experimental augmentations and a private army.', 50, 'Boss', '{"STR": 35, "PER": 30, "DEX": 28, "MEM": 25, "INT": 28, "CHA": 22}', 2500, '["Executive Order", "Call Reinforcements", "Plasma Cannon", "Last Resort Protocol", "Corporate Immunity"]', '[{"item": "neural-blade", "rate": 0.15}, {"item": "stealth-field", "rate": 0.08}]', 3000, 2000, 30),
('terminus-core', 'Terminus Core', 'Ancient AI that predates the current civilization, awakened and hostile.', 60, 'Boss', '{"STR": 20, "PER": 40, "DEX": 25, "MEM": 45, "INT": 50, "CHA": 15}', 3500, '["Singularity Pulse", "Rewrite Reality", "Summon Constructs", "Time Dilation Field", "Final Calculation"]', '[{"item": "quantum-core", "rate": 0.1}, {"item": "artifact-data", "rate": 0.2}]', 5000, 3500, 31);

-- =====================================================
-- SAMPLE CONTRACTS
-- =====================================================
INSERT INTO litrpg_contracts (slug, title, description, contract_type, difficulty, level_requirement, objectives, rewards, time_limit, sort_order) VALUES
('clear-sector-7', 'Clear Sector 7', 'Eliminate all hostile entities in abandoned industrial sector.', 'bounty', 'routine', 5, '[{"type": "kill", "description": "Eliminate Patrol Drones", "target": 5, "current": 0}, {"type": "kill", "description": "Eliminate Rogue Bots", "target": 3, "current": 0}]', '{"xp": 500, "credits": 250, "items": ["medkit-basic"]}', '2 hours', 1),
('data-extraction', 'Data Extraction', 'Infiltrate corporate facility and retrieve encrypted data package.', 'extraction', 'hazardous', 15, '[{"type": "reach", "description": "Infiltrate Server Room", "target": 1, "current": 0}, {"type": "interact", "description": "Download Data", "target": 1, "current": 0}, {"type": "reach", "description": "Extract to Safe Zone", "target": 1, "current": 0}]', '{"xp": 1500, "credits": 1000, "items": ["hacking-kit"]}', '1 hour', 2),
('vip-escort', 'VIP Escort', 'Protect high-value target during transit through hostile territory.', 'escort', 'critical', 25, '[{"type": "protect", "description": "Keep VIP alive", "target": 1, "current": 0}, {"type": "reach", "description": "Reach extraction point", "target": 1, "current": 0}]', '{"xp": 3000, "credits": 2500, "items": ["nano-weave-suit"]}', '45 minutes', 3),
('terminus-hunt', 'Hunt the Terminus', 'Locate and neutralize the awakened Terminus Core before it achieves full power.', 'bounty', 'suicide', 55, '[{"type": "discover", "description": "Locate Terminus Core", "target": 1, "current": 0}, {"type": "kill", "description": "Destroy Terminus Core", "target": 1, "current": 0}]', '{"xp": 10000, "credits": 8000, "items": ["neural-amp", "exo-shell"]}', '4 hours', 4);

-- =====================================================
-- VERIFICATION QUERIES (run after import)
-- =====================================================
-- SELECT * FROM litrpg_classes ORDER BY tier, sort_order;
-- SELECT a.name, COUNT(t.id) as tier_count FROM litrpg_abilities a LEFT JOIN litrpg_ability_tiers t ON a.id = t.ability_id GROUP BY a.id;
-- SELECT rank, COUNT(*) as count FROM litrpg_monsters GROUP BY rank;
-- SELECT * FROM litrpg_contracts;
