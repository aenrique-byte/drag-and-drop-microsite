<?php
declare(strict_types=1);

// Set error handling first
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../bootstrap.php';

// Set JSON content type early
header('Content-Type: application/json');

echo "Testing gallery update API...\n";

try {
  // Test basic functions
  echo "1. Testing require_method...\n";
  require_method(['GET', 'POST']);
  echo "   ✓ require_method works\n";
  
  echo "2. Testing require_role...\n";
  require_role('editor');
  echo "   ✓ require_role works\n";
  
  echo "3. Testing database connection...\n";
  $pdo = db();
  echo "   ✓ Database connection works\n";
  
  echo "4. Testing simple query...\n";
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM images LIMIT 1");
  $stmt->execute();
  $result = $stmt->fetch();
  echo "   ✓ Query works, found " . $result['count'] . " images\n";
  
  echo "5. Testing pathinfo function...\n";
  $testPath = "test-image.jpg";
  $filename = pathinfo($testPath, PATHINFO_FILENAME);
  echo "   ✓ pathinfo works: '$testPath' -> '$filename'\n";
  
  echo "\nAll tests passed!\n";
  
} catch (Exception $e) {
  echo "Exception: " . $e->getMessage() . "\n";
  echo "File: " . $e->getFile() . "\n";
  echo "Line: " . $e->getLine() . "\n";
} catch (Throwable $e) {
  echo "Throwable: " . $e->getMessage() . "\n";
  echo "File: " . $e->getFile() . "\n";
  echo "Line: " . $e->getLine() . "\n";
}
