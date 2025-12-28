<?php
/**
 * User Login with Email Verification Check
 * 
 * Authenticates user and checks if email is verified
 * Includes rate limiting and session security
 */

session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Get base path for redirects
$basePath = defined('BASE_PATH') ? BASE_PATH : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $basePath . '/public/login.php');
    exit;
}

$email = trim(strtolower($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$redirect = isset($_POST['redirect']) ? trim($_POST['redirect']) : '';

// Rate limiting check
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitAction = 'login';
$maxAttempts = 5;
$windowSeconds = 300; // 5 minutes

if (!checkActionRateLimit($conn, $rateLimitAction, $ip, $maxAttempts, $windowSeconds)) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Too many login attempts. Please wait 5 minutes before trying again.'];
    header('Location: ' . $basePath . '/public/login.php');
    exit;
}

// Log this attempt
logActionRateLimitAttempt($conn, $rateLimitAction, $ip);

// Validate input
if (empty($email) || empty($password)) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Please enter both email and password'];
    header('Location: ' . $basePath . '/public/login.php');
    exit;
}

// Check if user exists
$stmt = $conn->prepare("SELECT id, name, email, password, theme, email_verified_at FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid email or password'];
    header('Location: ' . $basePath . '/public/login.php');
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($password, $user['password'])) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid email or password'];
    header('Location: ' . $basePath . '/public/login.php');
    exit;
}

// Check if email is verified
if (!$user['email_verified_at']) {
    // Store user info for verification page
    $_SESSION['pending_verification_user_id'] = $user['id'];
    $_SESSION['pending_verification_email'] = $user['email'];
    
    // Store redirect URL if provided
    if ($redirect) {
        $_SESSION['post_verification_redirect'] = $redirect;
    }
    
    // Generate and send new OTP
    require_once '../../src/MailHelper.php';
    
    $otp = MailHelper::generateOTP();
    $expiryMinutes = OTP_EXPIRY_MINUTES;
    
    // Invalidate previous OTPs
    $stmt = $conn->prepare("UPDATE email_verifications SET is_used = 1 WHERE user_id = ? AND is_used = 0");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $stmt->close();
    
    // Store new OTP - use MySQL's NOW() for timezone consistency
    $stmt = $conn->prepare("INSERT INTO email_verifications (user_id, email, otp, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))");
    $stmt->bind_param("issi", $user['id'], $email, $otp, $expiryMinutes);
    $stmt->execute();
    $stmt->close();
    
    // Send verification email
    MailHelper::sendOTPEmail($email, $user['name'], $otp);
    
    $_SESSION['toast'] = ['type' => 'warning', 'message' => 'Please verify your email first. A new code has been sent.'];
    header('Location: ' . $basePath . '/public/verify-email.php');
    exit;
}

// Regenerate session ID to prevent session fixation attacks
session_regenerate_id(true);

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['theme'] = $user['theme'];
$_SESSION['theme_color'] = $user['theme_color'] ?? 'purple';

// Generate CSRF token for the new session
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Clear any pending verification data
unset($_SESSION['pending_verification_user_id']);
unset($_SESSION['pending_verification_email']);

// Validate redirect URL (must be relative and start with expected paths)
if ($redirect && preg_match('/^(share\.php|board\.php|workspace\.php|dashboard\.php)/', $redirect)) {
    header('Location: ' . $basePath . '/public/' . $redirect);
} else {
    header('Location: ' . $basePath . '/public/dashboard.php');
}
exit;
