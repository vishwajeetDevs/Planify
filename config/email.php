<?php
/**
 * Email/SMTP Configuration for Planify
 * 
 * This file loads email configuration from environment variables.
 * Uses SMTP (Gmail by default) with App Password authentication.
 */

// Ensure environment is loaded
if (!class_exists('Env')) {
    require_once __DIR__ . '/env.php';
    Env::load();
}

// =============================================================================
// SMTP SERVER SETTINGS (from .env)
// =============================================================================
define('MAIL_DRIVER', env('MAIL_DRIVER', 'smtp'));
define('MAIL_HOST', env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', env('MAIL_PORT', 587));
define('MAIL_USERNAME', env('MAIL_USERNAME', ''));
define('MAIL_PASSWORD', env('MAIL_PASSWORD', ''));
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls')); // tls or ssl

// =============================================================================
// SENDER INFORMATION (from .env)
// =============================================================================
define('MAIL_FROM', env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME', '')));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'Planify'));

// =============================================================================
// VERIFICATION SETTINGS (from .env)
// =============================================================================
define('OTP_EXPIRY_MINUTES', env('OTP_EXPIRY_MINUTES', 10));      // OTP valid for 10 minutes
define('OTP_LENGTH', env('OTP_LENGTH', 6));                        // 6-digit OTP
define('PASSWORD_RESET_EXPIRY_HOURS', env('PASSWORD_RESET_EXPIRY_HOURS', 1)); // Reset link valid for 1 hour

// =============================================================================
// APPLICATION URL (from db.php or .env)
// =============================================================================
// APP_URL is defined in db.php, but we provide a fallback here
if (!defined('APP_URL')) {
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = in_array($httpHost, ['localhost', '127.0.0.1']) 
               || strpos($httpHost, 'localhost:') === 0;

    if ($isLocal) {
        define('APP_URL', 'http://localhost/planify');
    } else {
        define('APP_URL', 'https://planify-task.great-site.net');
    }
}

// APP_NAME fallback
if (!defined('APP_NAME')) {
    define('APP_NAME', env('APP_NAME', 'Planify'));
}

// =============================================================================
// MAIL VALIDATION
// =============================================================================
/**
 * Check if mail configuration is valid
 * 
 * @return bool True if mail is properly configured
 */
function isMailConfigured(): bool {
    return !empty(MAIL_HOST) && 
           !empty(MAIL_USERNAME) && 
           !empty(MAIL_PASSWORD) &&
           !empty(MAIL_FROM);
}

/**
 * Get mail configuration status message
 * 
 * @return string Status message
 */
function getMailConfigStatus(): string {
    if (isMailConfigured()) {
        return "Mail configured: " . MAIL_HOST . " (Port: " . MAIL_PORT . ")";
    }
    
    $missing = [];
    if (empty(MAIL_HOST)) $missing[] = 'MAIL_HOST';
    if (empty(MAIL_USERNAME)) $missing[] = 'MAIL_USERNAME';
    if (empty(MAIL_PASSWORD)) $missing[] = 'MAIL_PASSWORD';
    if (empty(MAIL_FROM)) $missing[] = 'MAIL_FROM_ADDRESS';
    
    return "Mail not configured. Missing: " . implode(', ', $missing);
}
