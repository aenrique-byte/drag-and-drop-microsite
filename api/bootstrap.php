<?php
// Bootstrap file for Author CMS API
require_once __DIR__ . '/config.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// CORS headers (dynamic origin for credentials support)
$allowedOrigins = array_map('trim', explode(',', CORS_ORIGINS));
$requestOrigin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowCreds     = true;

if ($requestOrigin && in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Vary: Origin');
} elseif (count($allowedOrigins) === 1 && $allowedOrigins[0] === '*') {
    // Wildcard: cannot use credentials (CORS spec violation)
    header('Access-Control-Allow-Origin: *');
    $allowCreds = false;
}

// Only send credentials header if we're not using wildcard
if ($allowCreds) {
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
// Expose rate limit headers to browser JavaScript
header('Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Retry-After');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Max-Age: 600'); // Cache preflight for 10 minutes
    http_response_code(204);
    exit();
}

// Database connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Strict SQL mode for data integrity
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES'");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Database connection failed'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

// Utility functions
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function generateHash($password) {
    // Prefer Argon2id if available (more secure than bcrypt)
    if (defined('PASSWORD_ARGON2ID')) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyHash($password, $hash) {
    return password_verify($password, $hash);
}

// CIDR-aware IP matching for trusted proxy verification
function ipInCidr($ip, $cidr) {
    [$subnet, $mask] = array_pad(explode('/', $cidr), 2, null);
    if ($mask === null) return false;
    $ipBin     = inet_pton($ip);
    $subnetBin = inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false) return false;
    $maskBytes = intdiv((int)$mask, 8);
    $maskBits  = (int)$mask % 8;

    if ($maskBytes && substr($ipBin, 0, $maskBytes) !== substr($subnetBin, 0, $maskBytes)) return false;
    if ($maskBits === 0) return true;

    $ipByte     = ord($ipBin[$maskBytes] ?? "\0");
    $subnetByte = ord($subnetBin[$maskBytes] ?? "\0");
    $maskVal    = (~((1 << (8 - $maskBits)) - 1)) & 0xFF;
    return ($ipByte & $maskVal) === ($subnetByte & $maskVal);
}

function isFromTrustedProxy($remoteAddr, $trustedProxies) {
    foreach ($trustedProxies as $cidrOrIp) {
        if (strpos($cidrOrIp, '/') !== false) {
            if (ipInCidr($remoteAddr, $cidrOrIp)) return true;
        } elseif ($remoteAddr === $cidrOrIp) {
            return true;
        }
    }
    return false;
}

function getClientIP() {
    // Fill this with your proxy/load balancer ranges
    // Cloudflare IPv4/IPv6 CIDRs (update from https://www.cloudflare.com/ips/)
    $trustedProxies = [
        // Cloudflare IPv4 ranges
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        // Cloudflare IPv6 ranges
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
        // Add your custom proxy IPs if needed
        // '203.0.113.10',
    ];
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (filter_var($remoteAddr, FILTER_VALIDATE_IP) && isFromTrustedProxy($remoteAddr, $trustedProxies)) {
        // Prefer Cloudflare's header when behind Cloudflare
        $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
        if ($cf && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;

        // Else safely parse XFF (left-most hop)
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff) {
            $parts = array_map('trim', explode(',', $xff));
            $candidate = $parts[0] ?? '';
            if (filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
        }
    }

    // Fallback to REMOTE_ADDR
    if (!filter_var($remoteAddr, FILTER_VALIDATE_IP)) return '0.0.0.0';
    return $remoteAddr === '::1' ? '127.0.0.1' : $remoteAddr;
}

function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function getUserAgentHash() {
    return hash('sha256', getUserAgent());
}

// Session management with security hardening
session_set_cookie_params([
    'lifetime' => 0,              // Expires when browser closes
    'path'     => '/',
    'domain'   => '',             // Current domain
    'secure'   => true,           // HTTPS only (set to false for local dev)
    'httponly' => true,           // Prevent JavaScript access
    'samesite' => 'Lax',          // CSRF protection ('Strict' if feasible)
]);
session_name('authorcms');        // Non-default session name
session_start();

// Session timeout (30 minutes of inactivity)
$_SESSION['last_activity'] = $_SESSION['last_activity'] ?? time();
if (time() - $_SESSION['last_activity'] > 1800) {
    // Session expired
    session_unset();
    session_destroy();
    session_start(); // Start fresh session
} else {
    $_SESSION['last_activity'] = time();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        json_error('Authentication required', 401);
    }
}

function requireRole($role) {
    requireAuth();
    if ($_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'admin') {
        json_error('Insufficient permissions', 403);
    }
}

// Database helper
function db() {
    global $pdo;
    return $pdo;
}

// JSON response helpers
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

function json_response($data, $status = 200) {
    jsonResponse($data, $status);
}

function json_error($message, $status = 400, $extra = []) {
    $response = ['error' => $message];
    if (!empty($extra)) {
        $response = array_merge($response, $extra);
    }
    jsonResponse($response, $status);
}

// HTTP method validation
function require_method($methods) {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        if (!headers_sent()) {
            header('Allow: ' . implode(', ', $methods));
        }
        json_error('Method not allowed', 405);
    }
}

// Body JSON parsing
function body_json() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_error('Invalid JSON in request body', 400);
    }
    return $data ?: [];
}

// Authentication helper that returns user data
function require_auth() {
    requireAuth();
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        json_error('User not found', 401);
    }
    return $user;
}

// Rate limiting functionality (fixed-window, atomic, no race condition)
// Returns: array | null on DB error
function rateLimitAllow($key, $limit, $windowSeconds = 60) {
    global $pdo;
    static $stmtUpsert = null, $stmtSelect = null;

    $now = time();
    $bucketStart = intdiv($now, $windowSeconds) * $windowSeconds; // align to window
    $retryAfter  = ($bucketStart + $windowSeconds) - $now;

    // Normalize & cap key: lowercase, max 191 chars
    $key = substr(strtolower($key), 0, 191);

    try {
        // Prepare statements once for performance
        if (!$stmtUpsert) {
            $stmtUpsert = $pdo->prepare("
                INSERT INTO rate_limit_agg (key_name, window_start, count)
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE count = LEAST(count + 1, 1000000000)
            ");
            $stmtSelect = $pdo->prepare("
                SELECT count FROM rate_limit_agg
                WHERE key_name = ? AND window_start = ?
            ");
        }

        // Atomic upsert: one row per (key, bucket). Concurrency-safe.
        $stmtUpsert->execute([$key, $bucketStart]);

        // Fetch current count
        $stmtSelect->execute([$key, $bucketStart]);
        $cnt = (int)$stmtSelect->fetchColumn();

        $allowed = $cnt <= $limit;

        return [
            'allowed'       => $allowed,
            'count'         => $cnt,
            'limit'         => $limit,
            'remaining'     => max(0, $limit - $cnt),
            'retry_after'   => $allowed ? 0 : $retryAfter,
            'window_start'  => $bucketStart,
            'window_end'    => $bucketStart + $windowSeconds,
            'window_length' => $windowSeconds,
            'now'           => $now,
        ];
    } catch (Throwable $e) {
        error_log("Rate limit error: " . $e->getMessage());
        return null; // signal failure to caller
    }
}

function requireRateLimit($action, $limit, $windowSeconds = 60, $userId = null, $failClosed = false) {
    $ip = getClientIP();
    $parts = [$action, $ip];
    if ($userId) $parts[] = $userId;
    $key = implode(':', $parts);

    $res = rateLimitAllow($key, $limit, $windowSeconds);

    if ($res === null) {
        // DB problem: choose policy
        if ($failClosed) {
            if (!headers_sent()) {
                http_response_code(503);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['error' => 'Service temporarily unavailable']);
            exit;
        }
        // Fail open for non-critical actions
        return;
    }

    // Common headers for both allowed & blocked responses
    if (!headers_sent()) {
        header('X-RateLimit-Limit: ' . $res['limit']);
        header('X-RateLimit-Remaining: ' . $res['remaining']);
        header('X-RateLimit-Reset: ' . $res['window_end']);
    }

    if (!$res['allowed']) {
        // Structured logging for observability (hash userId for privacy)
        error_log(json_encode([
            'event'       => 'rate_limit_hit',
            'key'         => substr($key, 0, 191), // Match DB constraint
            'action'      => $action,
            'ip'          => $ip,
            'user_id'     => $userId ? substr(hash('sha256', $userId), 0, 16) : null,
            'count'       => $res['count'],
            'limit'       => $res['limit'],
            'retry_after' => $res['retry_after'],
            'ts'          => time(),
        ], JSON_UNESCAPED_SLASHES));

        if (!headers_sent()) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . $res['retry_after']);
            header('X-RateLimit-Limit: ' . $res['limit']);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . $res['window_end']);
        }
        echo json_encode([
            'error'        => 'Too many requests. Please try again later.',
            'retry_after'  => $res['retry_after'],
            'limit'        => $res['limit'],
            'remaining'    => 0,
            'window_end'   => $res['window_end'],
        ]);
        exit;
    }
}
?>
