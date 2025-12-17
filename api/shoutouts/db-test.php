<?php
/**
 * Standalone Database Test Script for Shoutouts
 * Tests database connectivity without relying on bootstrap.php
 * 
 * Access via: /api/shoutouts/db-test.php
 */

header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html>
<html>
<head>
    <title>Shoutouts DB Test</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #1e293b; color: #e2e8f0; }
        .success { color: #4ade80; }
        .error { color: #f87171; }
        .warning { color: #fbbf24; }
        .info { color: #60a5fa; }
        pre { background: #0f172a; padding: 15px; border-radius: 8px; overflow-x: auto; }
        h1 { border-bottom: 2px solid #334155; padding-bottom: 10px; }
        h2 { color: #94a3b8; margin-top: 30px; }
        .test { background: #334155; padding: 15px; margin: 10px 0; border-radius: 8px; }
        .test-name { font-weight: bold; margin-bottom: 5px; }
    </style>
</head>
<body>
<h1>🔧 Shoutouts Database Test</h1>
';

// Test 1: Check if config.php exists
echo '<div class="test"><div class="test-name">Test 1: Config File</div>';
$configPath = dirname(__DIR__) . '/config.php';
if (file_exists($configPath)) {
    echo '<span class="success">✓ Config file found at: ' . htmlspecialchars($configPath) . '</span>';
    
    // Load config
    require_once $configPath;
    
    echo '<br><span class="info">• DB_HOST: ' . (defined('DB_HOST') ? DB_HOST : '<span class="error">NOT DEFINED</span>') . '</span>';
    echo '<br><span class="info">• DB_NAME: ' . (defined('DB_NAME') ? DB_NAME : '<span class="error">NOT DEFINED</span>') . '</span>';
    echo '<br><span class="info">• DB_USER: ' . (defined('DB_USER') ? DB_USER : '<span class="error">NOT DEFINED</span>') . '</span>';
    echo '<br><span class="info">• DB_PASS: ' . (defined('DB_PASS') ? '********' : '<span class="error">NOT DEFINED</span>') . '</span>';
    echo '<br><span class="info">• DB_CHARSET: ' . (defined('DB_CHARSET') ? DB_CHARSET : '<span class="warning">NOT DEFINED (will default)</span>') . '</span>';
} else {
    echo '<span class="error">✗ Config file NOT FOUND at: ' . htmlspecialchars($configPath) . '</span>';
    echo '<br><span class="warning">Expected: api/config.php (copy from api/config.example.php)</span>';
}
echo '</div>';

// Test 2: Database connection
echo '<div class="test"><div class="test-name">Test 2: Database Connection</div>';
$pdo = null;
try {
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        throw new Exception('Database constants not defined. Check config.php');
    }
    
    $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . $charset;
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    echo '<span class="success">✓ Database connection successful!</span>';
    
    // Get MySQL version
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo '<br><span class="info">• MySQL Version: ' . htmlspecialchars($version) . '</span>';
    
} catch (PDOException $e) {
    echo '<span class="error">✗ Database connection FAILED</span>';
    echo '<br><pre class="error">' . htmlspecialchars($e->getMessage()) . '</pre>';
} catch (Exception $e) {
    echo '<span class="error">✗ ' . htmlspecialchars($e->getMessage()) . '</span>';
}
echo '</div>';

// Test 3: Check shoutout tables
if ($pdo) {
    echo '<div class="test"><div class="test-name">Test 3: Shoutout Tables</div>';
    
    $tables = [
        'shoutout_config' => 'Configuration settings',
        'shoutout_stories' => 'Royal Road stories',
        'shoutout_admin_shoutouts' => 'Shoutout code templates',
        'shoutout_availability' => 'Available booking dates',
        'shoutout_bookings' => 'Booking requests',
        'email_config' => 'Email/SMTP settings'
    ];
    
    $allTablesExist = true;
    foreach ($tables as $table => $description) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->fetch();
            
            if ($exists) {
                $count = $pdo->query("SELECT COUNT(*) as cnt FROM $table")->fetch();
                echo '<span class="success">✓ ' . $table . '</span>';
                echo ' <span class="info">(' . $count['cnt'] . ' rows)</span>';
                echo ' <span style="color:#64748b"> - ' . $description . '</span><br>';
            } else {
                echo '<span class="error">✗ ' . $table . ' - TABLE MISSING!</span>';
                echo ' <span style="color:#64748b"> - ' . $description . '</span><br>';
                $allTablesExist = false;
            }
        } catch (Exception $e) {
            echo '<span class="error">✗ ' . $table . ' - Error: ' . htmlspecialchars($e->getMessage()) . '</span><br>';
            $allTablesExist = false;
        }
    }
    
    if (!$allTablesExist) {
        echo '<br><span class="warning">⚠ Run the migration: api/migrations/2025-12-11-add-shoutouts-schema.sql</span>';
    }
    echo '</div>';
}

// Test 4: Test bootstrap.php
echo '<div class="test"><div class="test-name">Test 4: Bootstrap Integration</div>';
$bootstrapPath = dirname(__DIR__) . '/bootstrap.php';
if (file_exists($bootstrapPath)) {
    echo '<span class="success">✓ Bootstrap file exists</span>';
    
    // Check the content of bootstrap.php for the fix
    $bootstrapContent = file_get_contents($bootstrapPath);
    if (strpos($bootstrapContent, "__DIR__ . '/config.php'") !== false) {
        echo '<br><span class="success">✓ Bootstrap uses absolute path for config.php (fixed)</span>';
    } else if (strpos($bootstrapContent, "require_once 'config.php'") !== false) {
        echo '<br><span class="error">✗ Bootstrap uses relative path - needs fix!</span>';
        echo '<br><span class="warning">Change: require_once \'config.php\';</span>';
        echo '<br><span class="success">To: require_once __DIR__ . \'/config.php\';</span>';
    }
} else {
    echo '<span class="error">✗ Bootstrap file NOT FOUND</span>';
}
echo '</div>';

// Test 5: Quick data summary
if ($pdo) {
    echo '<h2>📊 Data Summary</h2>';
    echo '<div class="test">';
    
    try {
        // Stories
        $stories = $pdo->query("SELECT id, title, color FROM shoutout_stories ORDER BY created_at")->fetchAll();
        echo '<strong>Stories (' . count($stories) . '):</strong><br>';
        if (count($stories) === 0) {
            echo '<span class="warning">No stories configured yet. Use admin panel to add one.</span><br>';
        } else {
            foreach ($stories as $story) {
                echo '• <span class="info">' . htmlspecialchars($story['title']) . '</span> (ID: ' . htmlspecialchars($story['id']) . ', Color: ' . $story['color'] . ')<br>';
            }
        }
        
        // Config
        echo '<br><strong>Config:</strong><br>';
        $config = $pdo->query("SELECT * FROM shoutout_config")->fetchAll();
        if (count($config) === 0) {
            echo '<span class="warning">No config set yet (defaults will apply)</span><br>';
        } else {
            foreach ($config as $row) {
                echo '• ' . htmlspecialchars($row['config_key']) . ': ' . htmlspecialchars($row['config_value']) . '<br>';
            }
        }
        
        // Bookings summary
        $bookings = $pdo->query("SELECT status, COUNT(*) as cnt FROM shoutout_bookings GROUP BY status")->fetchAll();
        echo '<br><strong>Bookings:</strong><br>';
        if (count($bookings) === 0) {
            echo '<span class="info">No bookings yet</span><br>';
        } else {
            foreach ($bookings as $row) {
                $class = $row['status'] === 'approved' ? 'success' : ($row['status'] === 'rejected' ? 'error' : 'warning');
                echo '• <span class="' . $class . '">' . $row['status'] . ': ' . $row['cnt'] . '</span><br>';
            }
        }
        
    } catch (Exception $e) {
        echo '<span class="error">Error fetching data: ' . htmlspecialchars($e->getMessage()) . '</span>';
    }
    
    echo '</div>';
}

echo '
<h2>🔗 Quick Links</h2>
<div class="test">
    <a href="/shoutouts" style="color:#60a5fa">→ Public Shoutouts Page</a><br>
    <a href="/admin" style="color:#60a5fa">→ Admin Panel</a><br>
    <a href="/api/shoutouts/config.php" style="color:#60a5fa">→ Test Config API</a><br>
    <a href="/api/shoutouts/stories.php" style="color:#60a5fa">→ Test Stories API</a>
</div>

<p style="color:#64748b; margin-top:30px;">Test completed at: ' . date('Y-m-d H:i:s T') . '</p>
</body>
</html>';
