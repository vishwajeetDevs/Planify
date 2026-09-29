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

// Lets database triggers record who made a change for live sync.
if (isset($conn) && isset($_SESSION['user_id'])) {
    $realtimeActorId = (int) $_SESSION['user_id'];
    $conn->query("SET @planify_user_id = {$realtimeActorId}");
}

// =============================================================================
// PUBLIC URLS (from .env)
// =============================================================================
// APP_URL is the origin (http://192.168.2.242). APP_BASE_PATH is the folder
// (/planify, or empty in production). Every absolute link is built from these.
$appUrls = planify_resolve_app_urls();
define('BASE_PATH', $appUrls['BASE_PATH']);
define('BASE_URL', $appUrls['BASE_URL']);
define('APP_URL', $appUrls['APP_URL']);

// Application name
if (!defined('APP_NAME')) {
    define('APP_NAME', env('APP_NAME', 'Planify'));
}

// Upload configuration
define('UPLOAD_PATH', dirname(__DIR__) . '/' . env('UPLOAD_PATH', 'uploads') . '/');
define('UPLOAD_URL', APP_URL . '/' . trim((string) env('UPLOAD_PATH', 'uploads'), '/') . '/');
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
