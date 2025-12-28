<?php
/**
 * Send Email Verification OTP
 * 
 * Generates and sends OTP to user's email for verification
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
$userId = $input['user_id'] ?? $_SESSION['pending_verification_user_id'] ?? null;
$email = $input['email'] ?? $_SESSION['pending_verification_email'] ?? null;

if (!$userId || !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'User ID and email are required']);
    exit;
}

try {
    // Get user details
    $stmt = $conn->prepare("SELECT id, name, email, email_verified_at FROM users WHERE id = ? AND email = ?");
    $stmt->bind_param("is", $userId, $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Check if already verified
    if ($user['email_verified_at']) {
        echo json_encode(['success' => false, 'message' => 'Email is already verified']);
        exit;
    }
    
    // Check for rate limiting (max 3 OTPs per 10 minutes)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM email_verifications 
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $rateCheck = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($rateCheck['count'] >= 3) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many requests. Please wait before requesting another OTP.']);
        exit;
    }
    
    // Generate OTP
    $otp = MailHelper::generateOTP();
    $expiryMinutes = OTP_EXPIRY_MINUTES;
    
    // Invalidate previous OTPs
    $stmt = $conn->prepare("UPDATE email_verifications SET is_used = 1 WHERE user_id = ? AND is_used = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    
    // Store new OTP - use MySQL's NOW() for timezone consistency
    $stmt = $conn->prepare("INSERT INTO email_verifications (user_id, email, otp, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))");
    $stmt->bind_param("issi", $userId, $email, $otp, $expiryMinutes);
    $stmt->execute();
    $stmt->close();
    
    // Send email
    $result = MailHelper::sendOTPEmail($email, $user['name'], $otp);
    
    if ($result['success']) {
        // Store in session for verification page
        $_SESSION['pending_verification_user_id'] = $userId;
        $_SESSION['pending_verification_email'] = $email;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Verification code sent to your email',
            'expires_in' => OTP_EXPIRY_MINUTES . ' minutes'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
    }
    
} catch (Exception $e) {
    error_log("Send Verification Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

