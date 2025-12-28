<?php
/**
 * Forgot Password
 * 
 * Sends password reset link to user's email
 */

session_start();
header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../src/MailHelper.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$email = trim(strtolower($input['email'] ?? ''));

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email is required']);
    exit;
}

try {
    // Find user by email
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Always return success message (don't reveal if email exists)
    $successMessage = 'If an account with that email exists, we\'ve sent a password reset link.';
    
    if (!$user) {
        // Don't reveal that user doesn't exist
        echo json_encode(['success' => true, 'message' => $successMessage]);
        exit;
    }
    
    // Check for rate limiting (max 3 requests per hour)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM password_resets 
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $rateCheck = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($rateCheck['count'] >= 3) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait before requesting another reset link.']);
        exit;
    }
    
    // Generate reset token
    $token = MailHelper::generateResetToken();
    $expiryHours = PASSWORD_RESET_EXPIRY_HOURS;
    
    // Invalidate previous tokens
    $stmt = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE user_id = ? AND is_used = 0");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $stmt->close();
    
    // Store new token - use MySQL's NOW() for timezone consistency
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, email, token, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))");
    $stmt->bind_param("issi", $user['id'], $email, $token, $expiryHours);
    $stmt->execute();
    $stmt->close();
    
    // Send email
    $result = MailHelper::sendPasswordResetEmail($email, $user['name'], $token);
    
    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => $successMessage]);
    } else {
        // Log error but still show success (security)
        error_log("Failed to send password reset email to: " . $email);
        echo json_encode(['success' => true, 'message' => $successMessage]);
    }
    
} catch (Exception $e) {
    error_log("Forgot Password Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

