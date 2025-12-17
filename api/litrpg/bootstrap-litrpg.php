<?php
/**
 * LitRPG API Bootstrap
 * Common setup for all LitRPG API endpoints
 */

require_once __DIR__ . '/../bootstrap.php';

/**
 * Check if user is authenticated as admin
 * @return bool
 */
function isAdmin(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Require admin authentication or exit with 401
 */
function requireAdmin(): void {
    if (!isAdmin()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

/**
 * Generate a slug from a string
 * @param string $text
 * @return string
 */
function generateSlug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Generate a unique slug for a table
 * @param PDO $pdo
 * @param string $table
 * @param string $baseSlug
 * @param int|null $excludeId ID to exclude from duplicate check (for updates)
 * @return string
 */
function generateUniqueSlug(PDO $pdo, string $table, string $baseSlug, ?int $excludeId = null): string {
    $slug = generateSlug($baseSlug);
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if (!$stmt->fetch()) {
            return $slug; // Slug is unique
        }
        
        // Add suffix and try again
        $slug = $originalSlug . '-' . $counter;
        $counter++;
        
        // Safety limit
        if ($counter > 100) {
            return $originalSlug . '-' . uniqid();
        }
    }
}
