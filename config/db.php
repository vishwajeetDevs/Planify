<?php
/**
 * Database Configuration for Planify
 * 
 * This file loads database credentials from environment variables
 * and establishes the database connection.
 */

// Load environment variables
require_once __DIR__ . '/env.php';
Env::load();

// =============================================================================
// DATABASE CONFIGURATION (from .env)
// =============================================================================
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', 3306));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASSWORD', ''));
define('DB_NAME', env('DB_NAME', 'planify'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Set timezone for consistency
date_default_timezone_set('Asia/Kolkata');

// Create database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    // Check connection
    if ($conn->connect_error) {
        if (Env::isDebug()) {
            die("Connection failed: " . $conn->connect_error);
        } else {
            error_log("Database connection failed: " . $conn->connect_error);
            die("Database connection error. Please try again later.");
        }
    }
    
    // Set charset
    $conn->set_charset(DB_CHARSET);
    
    // Sync MySQL timezone with PHP timezone
    $conn->query("SET time_zone = '+05:30'");
    
} catch (Exception $e) {
    if (Env::isDebug()) {
        die("Database connection error: " . $e->getMessage());
    } else {
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection error. Please try again later.");
    }
}

// =============================================================================
// SESSION CONFIGURATION (from .env)
// =============================================================================
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings from environment
    $sessionLifetime = env('SESSION_LIFETIME', 120) * 60; // Convert minutes to seconds
    $secureCookie = env('SESSION_SECURE_COOKIE', false);
    $httpOnly = env('SESSION_HTTP_ONLY', true);
    $sameSite = env('SESSION_SAME_SITE', 'Lax');
    
    // Set session cookie parameters
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => env('COOKIE_PATH', '/'),
        'domain' => env('COOKIE_DOMAIN', ''),
        'secure' => $secureCookie,
        'httponly' => $httpOnly,
        'samesite' => $sameSite
    ]);
    
    session_start();
}

// =============================================================================
// ENVIRONMENT CONFIGURATION
// =============================================================================
// Determine environment based on APP_ENV or auto-detect from hostname
$appEnv = env('APP_ENV', 'production');
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = in_array($httpHost, ['localhost', '127.0.0.1']) 
               || strpos($httpHost, 'localhost:') === 0;

// Known production domains (InfinityFree)
$productionDomains = ['planify-task.great-site.net', 'great-site.net', 'infinityfree.com', 'epizy.com'];
$isProduction = false;
foreach ($productionDomains as $domain) {
    if (strpos($httpHost, $domain) !== false) {
        $isProduction = true;
        break;
    }
}

// Override to development if on localhost
if ($isLocalhost) {
    $appEnv = 'development';
} elseif ($isProduction) {
    $appEnv = 'production';
}

// Set base path and URL based on environment
if ($appEnv === 'development' || $isLocalhost) {
    // LOCAL DEVELOPMENT - files in /planify subfolder
    define('BASE_PATH', env('APP_BASE_PATH', '/planify'));
    define('BASE_URL', 'http://localhost/planify/public');
    define('APP_URL', 'http://localhost/planify');
} else {
    // PRODUCTION (InfinityFree) - files at root, no subfolder
    define('BASE_PATH', '');
    define('BASE_URL', 'https://planify-task.great-site.net/public');
    define('APP_URL', 'https://planify-task.great-site.net');
}

// Application name
if (!defined('APP_NAME')) {
    define('APP_NAME', env('APP_NAME', 'Planify'));
}

// Upload configuration
define('UPLOAD_PATH', dirname(__DIR__) . '/' . env('UPLOAD_PATH', 'uploads') . '/');
define('UPLOAD_URL', BASE_URL . '/../' . env('UPLOAD_PATH', 'uploads') . '/');
define('UPLOAD_MAX_SIZE', env('UPLOAD_MAX_SIZE', 10485760)); // 10MB default

// Security settings
define('APP_KEY', env('APP_KEY', ''));
define('APP_DEBUG', env('APP_DEBUG', false));

// Validate critical environment variables in development
if (Env::isDevelopment()) {
    $missing = Env::validate(['DB_HOST', 'DB_NAME']);
    if (!empty($missing)) {
        error_log("Warning: Missing environment variables: " . implode(', ', $missing));
    }
}
?>
