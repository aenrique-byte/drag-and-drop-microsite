<?php
/**
 * Manage Class-Ability assignments
 * GET - List abilities for a class (or all class-abilities)
 * 
 * NOTE: Classes and abilities are now stored in constants files.
 * POST and DELETE operations are no longer supported.
 */

require_once __DIR__ . '/../bootstrap-litrpg.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Load classes from constants
            $constantsPath = __DIR__ . '/../../../src/features/litrpg/class-constants.ts';
            
            if (!file_exists($constantsPath)) {
                throw new Exception('Class constants file not found');
            }
            
            $constantsContent = file_get_contents($constantsPath);
            
            // Extract the classes array
            if (preg_match('/export const CLASSES.*?=\s*(\[.*?\]);/s', $constantsContent, $matches)) {
                $jsonString = $matches[1];
                
                // Clean up TypeScript syntax to make it valid JSON
                $jsonString = preg_replace('/(\w+):/s', '"$1":', $jsonString);
                $jsonString = preg_replace("/'/", '"', $jsonString);
                $jsonString = preg_replace('/,\s*\]/', ']', $jsonString);
                $jsonString = preg_replace('/,\s*\}/', '}', $jsonString);
                
                $classes = json_decode($jsonString, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Failed to parse classes: ' . json_last_error_msg());
                }
            } else {
                $classes = [];
            }
            
            // Build assignments array from classes
            $assignments = [];
            $classId = isset($_GET['class_id']) ? intval($_GET['class_id']) : null;
            
            foreach ($classes as $class) {
                if ($classId && $class['id'] !== $classId) {
                    continue;
                }
                
                if (isset($class['abilities']) && is_array($class['abilities'])) {
                    foreach ($class['abilities'] as $ability) {
                        $assignments[] = [
                            'class_id' => $class['id'],
                            'ability_id' => $ability['id'],
                            'class_name' => $class['name'],
                            'class_tier' => $class['tier'],
                            'ability_name' => $ability['name'],
                            'ability_description' => $ability['description'] ?? '',
                            'max_level' => 10
                        ];
                    }
                }
            }
            
            echo json_encode([
                'success' => true,
                'assignments' => $assignments
            ]);
            break;
            
        case 'POST':
            // Add ability to class
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin access required']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $classId = isset($input['class_id']) ? intval($input['class_id']) : 0;
            $abilityId = isset($input['ability_id']) ? intval($input['ability_id']) : 0;
            
            if (!$classId || !$abilityId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'class_id and ability_id required']);
                exit;
            }
            
            // Check if already exists
            $checkStmt = $pdo->prepare("SELECT id FROM litrpg_class_abilities WHERE class_id = ? AND ability_id = ?");
            $checkStmt->execute([$classId, $abilityId]);
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Ability already assigned to this class']);
                exit;
            }
            
            // Insert
            $stmt = $pdo->prepare("INSERT INTO litrpg_class_abilities (class_id, ability_id) VALUES (?, ?)");
            $stmt->execute([$classId, $abilityId]);
            
            echo json_encode([
                'success' => true,
                'link_id' => $pdo->lastInsertId(),
                'message' => 'Ability assigned to class'
            ]);
            break;
            
        case 'DELETE':
            // Remove ability from class
            if (!isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin access required']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $linkId = isset($input['link_id']) ? intval($input['link_id']) : 0;
            $classId = isset($input['class_id']) ? intval($input['class_id']) : 0;
            $abilityId = isset($input['ability_id']) ? intval($input['ability_id']) : 0;
            
            if ($linkId) {
                // Delete by link ID
                $stmt = $pdo->prepare("DELETE FROM litrpg_class_abilities WHERE id = ?");
                $stmt->execute([$linkId]);
            } elseif ($classId && $abilityId) {
                // Delete by class_id + ability_id
                $stmt = $pdo->prepare("DELETE FROM litrpg_class_abilities WHERE class_id = ? AND ability_id = ?");
                $stmt->execute([$classId, $abilityId]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'link_id or (class_id + ability_id) required']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Ability removed from class'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
