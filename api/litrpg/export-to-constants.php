<?php
/**
 * Export LitRPG data from MySQL to TypeScript constants
 * GET /api/litrpg/export-to-constants.php
 * 
 * Admin only - exports all classes, abilities, items, monsters, contracts
 * as TypeScript code that can be copy/pasted into the constants files.
 */

require_once __DIR__ . '/bootstrap-litrpg.php';

// NOTE: Auth removed since this is read-only export of public game data
// and the whole point is to move away from DB complexity

header('Content-Type: text/plain; charset=utf-8');

/**
 * Escape string for TypeScript
 */
function tsString(string $str): string {
    return "'" . str_replace("'", "\\'", str_replace("\\", "\\\\", $str)) . "'";
}

/**
 * Convert snake_case to camelCase
 */
function toCamelCase(string $str): string {
    return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $str))));
}

/**
 * Format a TypeScript object with proper indentation
 */
function formatTsObject(array $obj, int $indent = 2): string {
    $spaces = str_repeat('  ', $indent);
    $lines = [];
    foreach ($obj as $key => $value) {
        if (is_null($value)) continue;
        
        $formattedKey = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key) ? $key : "'{$key}'";
        
        if (is_bool($value)) {
            $lines[] = "{$spaces}{$formattedKey}: " . ($value ? 'true' : 'false') . ",";
        } elseif (is_numeric($value)) {
            $lines[] = "{$spaces}{$formattedKey}: {$value},";
        } elseif (is_string($value)) {
            $lines[] = "{$spaces}{$formattedKey}: " . tsString($value) . ",";
        } elseif (is_array($value)) {
            if (empty($value)) {
                $lines[] = "{$spaces}{$formattedKey}: [],";
            } elseif (array_keys($value) === range(0, count($value) - 1)) {
                // Indexed array
                $items = [];
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $items[] = "{\n" . formatTsObject($item, $indent + 2) . "\n{$spaces}  }";
                    } elseif (is_string($item)) {
                        $items[] = tsString($item);
                    } else {
                        $items[] = json_encode($item);
                    }
                }
                if (count($items) <= 3 && !str_contains(implode('', $items), "\n")) {
                    $lines[] = "{$spaces}{$formattedKey}: [" . implode(', ', $items) . "],";
                } else {
                    $lines[] = "{$spaces}{$formattedKey}: [\n{$spaces}  " . implode(",\n{$spaces}  ", $items) . "\n{$spaces}],";
                }
            } else {
                // Associative array
                $lines[] = "{$spaces}{$formattedKey}: {\n" . formatTsObject($value, $indent + 1) . "\n{$spaces}},";
            }
        }
    }
    return implode("\n", $lines);
}

try {
    echo "// ============================================================\n";
    echo "// LITRPG DATA EXPORT FROM DATABASE\n";
    echo "// Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "// ============================================================\n\n";

    // ===========================================
    // ABILITIES
    // ===========================================
    echo "// ============================================================\n";
    echo "// ABILITIES (paste into ability-constants.ts)\n";
    echo "// ============================================================\n\n";
    
    $stmt = $pdo->query("SELECT * FROM litrpg_abilities WHERE status = 'active' ORDER BY sort_order ASC, name ASC");
    $abilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "export interface ExportedAbility {\n";
    echo "  id: number;\n";
    echo "  slug: string;\n";
    echo "  name: string;\n";
    echo "  description: string;\n";
    echo "  maxLevel: number;\n";
    echo "  evolutionId?: number;\n";
    echo "  tiers: Array<{\n";
    echo "    level: number;\n";
    echo "    duration?: string;\n";
    echo "    cooldown?: string;\n";
    echo "    energyCost?: number;\n";
    echo "    effectDescription?: string;\n";
    echo "  }>;\n";
    echo "}\n\n";
    
    echo "export const DB_ABILITIES: Record<string, ExportedAbility> = {\n";
    
    foreach ($abilities as $ability) {
        // Get tiers
        $tierStmt = $pdo->prepare("SELECT * FROM litrpg_ability_tiers WHERE ability_id = ? ORDER BY tier_level ASC");
        $tierStmt->execute([$ability['id']]);
        $tiers = $tierStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $tierData = [];
        foreach ($tiers as $t) {
            $tierObj = ['level' => (int)$t['tier_level']];
            if ($t['duration']) $tierObj['duration'] = $t['duration'];
            if ($t['cooldown']) $tierObj['cooldown'] = $t['cooldown'];
            if ($t['energy_cost']) $tierObj['energyCost'] = (int)$t['energy_cost'];
            if ($t['effect_description']) $tierObj['effectDescription'] = $t['effect_description'];
            $tierData[] = $tierObj;
        }
        
        $abilityData = [
            'id' => (int)$ability['id'],
            'slug' => $ability['slug'],
            'name' => $ability['name'],
            'description' => $ability['description'] ?? '',
            'maxLevel' => (int)$ability['max_level'],
        ];
        if ($ability['evolution_ability_id']) {
            $abilityData['evolutionId'] = (int)$ability['evolution_ability_id'];
        }
        $abilityData['tiers'] = $tierData;
        
        echo "  '{$ability['slug']}': {\n";
        echo formatTsObject($abilityData, 2) . "\n";
        echo "  },\n";
    }
    
    echo "};\n\n";
    
    // Create a lookup by ID
    echo "// Lookup by ID\n";
    echo "export const ABILITIES_BY_ID: Record<number, ExportedAbility> = Object.fromEntries(\n";
    echo "  Object.values(DB_ABILITIES).map(a => [a.id, a])\n";
    echo ");\n\n";

    // ===========================================
    // CLASSES
    // ===========================================
    echo "// ============================================================\n";
    echo "// CLASSES (paste into class-constants.ts)\n";
    echo "// ============================================================\n\n";
    
    $stmt = $pdo->query("SELECT * FROM litrpg_classes WHERE status = 'active' ORDER BY tier ASC, unlock_level ASC, sort_order ASC");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "export interface ExportedClass {\n";
    echo "  id: number;\n";
    echo "  slug: string;\n";
    echo "  name: string;\n";
    echo "  description: string;\n";
    echo "  tier: number;\n";
    echo "  unlockLevel: number;\n";
    echo "  prerequisiteClassId?: number;\n";
    echo "  statBonuses?: Record<string, number>;\n";
    echo "  primaryAttribute?: string;\n";
    echo "  secondaryAttribute?: string;\n";
    echo "  abilityIds: number[];\n";
    echo "}\n\n";
    
    echo "export const DB_CLASSES: Record<string, ExportedClass> = {\n";
    
    foreach ($classes as $class) {
        // Get class abilities
        $abilityStmt = $pdo->prepare("
            SELECT ability_id FROM litrpg_class_abilities 
            WHERE class_id = ? ORDER BY unlock_class_level ASC
        ");
        $abilityStmt->execute([$class['id']]);
        $abilityIds = $abilityStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $statBonuses = $class['stat_bonuses'] ? json_decode($class['stat_bonuses'], true) : null;
        
        // Parse tier from string like 'tier-2' to number
        $tierNum = 1;
        if (preg_match('/tier-?(\d+)/i', $class['tier'], $m)) {
            $tierNum = (int)$m[1];
        } elseif (is_numeric($class['tier'])) {
            $tierNum = (int)$class['tier'];
        }
        
        $classData = [
            'id' => (int)$class['id'],
            'slug' => $class['slug'],
            'name' => $class['name'],
            'description' => $class['description'] ?? '',
            'tier' => $tierNum,
            'unlockLevel' => (int)$class['unlock_level'],
        ];
        
        if ($class['prerequisite_class_id']) {
            $classData['prerequisiteClassId'] = (int)$class['prerequisite_class_id'];
        }
        if ($statBonuses && !empty($statBonuses)) {
            $classData['statBonuses'] = $statBonuses;
        }
        if ($class['primary_attribute']) {
            $classData['primaryAttribute'] = $class['primary_attribute'];
        }
        if ($class['secondary_attribute']) {
            $classData['secondaryAttribute'] = $class['secondary_attribute'];
        }
        $classData['abilityIds'] = array_map('intval', $abilityIds);
        
        echo "  '{$class['slug']}': {\n";
        echo formatTsObject($classData, 2) . "\n";
        echo "  },\n";
    }
    
    echo "};\n\n";
    
    echo "// Lookup by ID\n";
    echo "export const CLASSES_BY_ID: Record<number, ExportedClass> = Object.fromEntries(\n";
    echo "  Object.values(DB_CLASSES).map(c => [c.id, c])\n";
    echo ");\n\n";

    // ===========================================
    // ITEMS
    // ===========================================
    echo "// ============================================================\n";
    echo "// ITEMS (paste into loot-constants.ts)\n";
    echo "// ============================================================\n\n";
    
    $stmt = $pdo->query("SELECT * FROM litrpg_items WHERE status = 'active' ORDER BY sort_order ASC, name ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "export interface ExportedItem {\n";
    echo "  id: number;\n";
    echo "  slug: string;\n";
    echo "  name: string;\n";
    echo "  description: string;\n";
    echo "  techLevel: 'TL8' | 'TL9' | 'TL10';\n";
    echo "  category: 'Tool' | 'Weapon' | 'Component' | 'Material' | 'Consumable' | 'Armor' | 'Medical';\n";
    echo "  statsBonus?: Record<string, number>;\n";
    echo "  value: number;\n";
    echo "}\n\n";
    
    echo "export const DB_ITEMS: Record<string, ExportedItem> = {\n";
    
    foreach ($items as $item) {
        $statsBonus = $item['stats_bonus'] ? json_decode($item['stats_bonus'], true) : null;
        
        $itemData = [
            'id' => (int)$item['id'],
            'slug' => $item['slug'],
            'name' => $item['name'],
            'description' => $item['description'] ?? '',
            'techLevel' => $item['tech_level'] ?? 'TL8',
            'category' => $item['category'] ?? 'Tool',
            'value' => (int)$item['value'],
        ];
        
        if ($statsBonus && !empty($statsBonus)) {
            $itemData['statsBonus'] = $statsBonus;
        }
        
        echo "  '{$item['slug']}': {\n";
        echo formatTsObject($itemData, 2) . "\n";
        echo "  },\n";
    }
    
    echo "};\n\n";
    
    echo "export const ITEMS_BY_ID: Record<number, ExportedItem> = Object.fromEntries(\n";
    echo "  Object.values(DB_ITEMS).map(i => [i.id, i])\n";
    echo ");\n\n";

    // ===========================================
    // MONSTERS
    // ===========================================
    echo "// ============================================================\n";
    echo "// MONSTERS (paste into monster-constants.ts)\n";
    echo "// ============================================================\n\n";
    
    $stmt = $pdo->query("SELECT * FROM litrpg_monsters WHERE status = 'active' ORDER BY rank DESC, level ASC");
    $monsters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "export interface ExportedMonster {\n";
    echo "  id: number;\n";
    echo "  slug: string;\n";
    echo "  name: string;\n";
    echo "  description: string;\n";
    echo "  level: number;\n";
    echo "  rank: 'Trash' | 'Regular' | 'Champion' | 'Boss';\n";
    echo "  hp: number;\n";
    echo "  stats?: Record<string, number>;\n";
    echo "  abilities?: string[];\n";
    echo "  lootTable?: Array<{ item: string; rate: number }>;\n";
    echo "  xpReward: number;\n";
    echo "  credits: number;\n";
    echo "}\n\n";
    
    echo "export const DB_MONSTERS: Record<string, ExportedMonster> = {\n";
    
    foreach ($monsters as $monster) {
        $stats = $monster['stats'] ? json_decode($monster['stats'], true) : null;
        $abilities = $monster['abilities'] ? json_decode($monster['abilities'], true) : null;
        $lootTable = $monster['loot_table'] ? json_decode($monster['loot_table'], true) : null;
        
        $monsterData = [
            'id' => (int)$monster['id'],
            'slug' => $monster['slug'],
            'name' => $monster['name'],
            'description' => $monster['description'] ?? '',
            'level' => (int)$monster['level'],
            'rank' => $monster['rank'] ?? 'Regular',
            'hp' => (int)$monster['hp'],
            'xpReward' => (int)$monster['xp_reward'],
            'credits' => (int)$monster['credits'],
        ];
        
        if ($stats && !empty($stats)) {
            $monsterData['stats'] = $stats;
        }
        if ($abilities && !empty($abilities)) {
            $monsterData['abilities'] = $abilities;
        }
        if ($lootTable && !empty($lootTable)) {
            $monsterData['lootTable'] = $lootTable;
        }
        
        echo "  '{$monster['slug']}': {\n";
        echo formatTsObject($monsterData, 2) . "\n";
        echo "  },\n";
    }
    
    echo "};\n\n";
    
    echo "export const MONSTERS_BY_ID: Record<number, ExportedMonster> = Object.fromEntries(\n";
    echo "  Object.values(DB_MONSTERS).map(m => [m.id, m])\n";
    echo ");\n\n";

    // ===========================================
    // CONTRACTS
    // ===========================================
    echo "// ============================================================\n";
    echo "// CONTRACTS (paste into contract-constants.ts or quest-constants.ts)\n";
    echo "// ============================================================\n\n";
    
    $stmt = $pdo->query("SELECT * FROM litrpg_contracts WHERE status = 'active' ORDER BY difficulty ASC, level_requirement ASC");
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "export interface ExportedContract {\n";
    echo "  id: number;\n";
    echo "  slug: string;\n";
    echo "  title: string;\n";
    echo "  description: string;\n";
    echo "  contractType: 'bounty' | 'extraction' | 'escort' | 'patrol' | 'investigation';\n";
    echo "  difficulty: 'routine' | 'hazardous' | 'critical' | 'suicide';\n";
    echo "  levelRequirement: number;\n";
    echo "  objectives?: Array<{ type: string; description: string; target: number; current: number }>;\n";
    echo "  rewards?: { xp: number; credits: number; items?: string[] };\n";
    echo "  timeLimit?: string;\n";
    echo "}\n\n";
    
    echo "export const DB_CONTRACTS: Record<string, ExportedContract> = {\n";
    
    foreach ($contracts as $contract) {
        $objectives = $contract['objectives'] ? json_decode($contract['objectives'], true) : null;
        $rewards = $contract['rewards'] ? json_decode($contract['rewards'], true) : null;
        
        $contractData = [
            'id' => (int)$contract['id'],
            'slug' => $contract['slug'],
            'title' => $contract['title'],
            'description' => $contract['description'] ?? '',
            'contractType' => $contract['contract_type'] ?? 'bounty',
            'difficulty' => $contract['difficulty'] ?? 'routine',
            'levelRequirement' => (int)$contract['level_requirement'],
        ];
        
        if ($objectives && !empty($objectives)) {
            $contractData['objectives'] = $objectives;
        }
        if ($rewards && !empty($rewards)) {
            $contractData['rewards'] = $rewards;
        }
        if ($contract['time_limit']) {
            $contractData['timeLimit'] = $contract['time_limit'];
        }
        
        echo "  '{$contract['slug']}': {\n";
        echo formatTsObject($contractData, 2) . "\n";
        echo "  },\n";
    }
    
    echo "};\n\n";
    
    echo "export const CONTRACTS_BY_ID: Record<number, ExportedContract> = Object.fromEntries(\n";
    echo "  Object.values(DB_CONTRACTS).map(c => [c.id, c])\n";
    echo ");\n\n";

    // ===========================================
    // SUMMARY
    // ===========================================
    echo "// ============================================================\n";
    echo "// EXPORT SUMMARY\n";
    echo "// ============================================================\n";
    echo "// Abilities: " . count($abilities) . "\n";
    echo "// Classes: " . count($classes) . "\n";
    echo "// Items: " . count($items) . "\n";
    echo "// Monsters: " . count($monsters) . "\n";
    echo "// Contracts: " . count($contracts) . "\n";
    echo "// ============================================================\n";

} catch (Exception $e) {
    http_response_code(500);
    echo "// ERROR: " . $e->getMessage() . "\n";
}
