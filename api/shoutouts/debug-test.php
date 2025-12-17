<?php
// Quick diagnostic to test database connection and tables
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: text/plain');

echo "=== SHOUTOUTS DEBUG TEST ===\n\n";

try {
    $pdo = db();
    echo "✓ Database connection successful\n";
    echo "Database: " . DB_NAME . "\n\n";

    // Check if shoutout tables exist
    $tables = [
        'shoutout_config',
        'shoutout_stories',
        'shoutout_admin_shoutouts',
        'shoutout_availability',
        'shoutout_bookings'
    ];

    echo "Checking tables:\n";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch();
        if ($exists) {
            $count = $pdo->query("SELECT COUNT(*) as cnt FROM $table")->fetch();
            echo "✓ $table exists (rows: {$count['cnt']})\n";
        } else {
            echo "✗ $table MISSING!\n";
        }
    }

    echo "\n=== END DEBUG ===\n";

} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
