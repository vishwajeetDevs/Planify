<?php
/**
 * User Registration with Email Verification
 * 
 * Creates new user account and sends verification email
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../../config/db.php";
require_once "../../includes/functions.php";
require_once "../../src/MailHelper.php";

// Get base path for redirects
$basePath = defined('BASE_PATH') ? BASE_PATH : '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid request method'];
    header('Location: ' . $basePath . '/public/register.php');
    exit;
}

// Get and sanitize input
$name = trim($_POST['name'] ?? '');
$email = filter_var(trim(strtolower($_POST['email'] ?? '')), FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$redirect = trim($_POST['redirect'] ?? '');

// Validate input
if (empty($name) || empty($email) || empty($password)) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'All fields are required'];
    header('Location: ' . $basePath . '/public/register.php');
    exit;
}

// Validate password confirmation
if ($password !== $confirmPassword) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Passwords do not match'];
    header('Location: ' . $basePath . '/public/register.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Invalid email format'];
    header('Location: ' . $basePath . '/public/register.php');
    exit;
}

if (strlen($password) < 8) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Password must be at least 8 characters long'];
    header('Location: ' . $basePath . '/public/register.php');
    exit;
}

// Check password complexity
if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number'];
    header('Location: ' . $basePath . '/public/register.php');
    exit;
}

// Rate limiting for registration
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkActionRateLimit($conn, 'register', $ip, 3, 3600)) { // Max 3 registrations per hour per IP
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'Too many registration attempts. Please try again later.'];
    header('Location: ' . $basePath . '/public/register.php');
    exit;
}
logActionRateLimitAttempt($conn, 'register', $ip);

try {
    global $conn;
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id, email_verified_at FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingUser = $result->fetch_assoc();
    $stmt->close();
    
    if ($existingUser) {
        // If user exists but not verified, allow re-registration (update password and resend OTP)
        if (!$existingUser['email_verified_at']) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name = ?, password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $name, $hashedPassword, $existingUser['id']);
            $stmt->execute();
            $stmt->close();
            
            $userId = $existingUser['id'];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Email already registered. Please login.'];
            header('Location: ' . $basePath . '/public/login.php');
            exit;
        }
    } else {
        // Hash password and insert new user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hashedPassword);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create user');
        }
        
        $userId = $conn->insert_id;
        $stmt->close();
    }
    
    // Generate OTP for email verification
    $otp = MailHelper::generateOTP();
    $expiryMinutes = OTP_EXPIRY_MINUTES;
    
    // Store OTP in database - use MySQL's NOW() for timezone consistency
    $stmt = $conn->prepare("INSERT INTO email_verifications (user_id, email, otp, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))");
    $stmt->bind_param("issi", $userId, $email, $otp, $expiryMinutes);
    $stmt->execute();
    $stmt->close();
    
    // Send verification email
    $emailResult = MailHelper::sendOTPEmail($email, $name, $otp);
    
    if ($emailResult['success']) {
        // Store user info in session for verification page
        $_SESSION['pending_verification_user_id'] = $userId;
        $_SESSION['pending_verification_email'] = $email;
        
        // Store redirect URL if provided
        if ($redirect) {
            $_SESSION['post_verification_redirect'] = $redirect;
        }
        
        // Redirect to verification page
        header('Location: ' . $basePath . '/public/verify-email.php');
    } else {
        // Email failed but user was created - show message and let them resend
        $_SESSION['pending_verification_user_id'] = $userId;
        $_SESSION['pending_verification_email'] = $email;
        $_SESSION['toast'] = ['type' => 'warning', 'message' => 'Account created but email delivery failed. Please resend the verification code.'];
        header('Location: ' . $basePath . '/public/verify-email.php');
    }
    
} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'An error occurred during registration'];
    header('Location: ' . $basePath . '/public/register.php');
}

exit;
