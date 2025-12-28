<?php
/**
 * Verify Email OTP
 * 
 * Verifies the OTP entered by user and marks email as verified
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
$otp = trim($input['otp'] ?? '');
$email = $input['email'] ?? $_SESSION['pending_verification_email'] ?? null;

if (!$otp || !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'OTP and email are required']);
    exit;
}

try {
    // Find valid OTP
    $stmt = $conn->prepare("
        SELECT ev.*, u.name as user_name 
        FROM email_verifications ev
        INNER JOIN users u ON ev.user_id = u.id
        WHERE ev.email = ? 
        AND ev.otp = ? 
        AND ev.is_used = 0 
        AND ev.expires_at > NOW()
        ORDER BY ev.created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    $verification = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$verification) {
        // Check if OTP exists but expired
        $stmt = $conn->prepare("
            SELECT * FROM email_verifications 
            WHERE email = ? AND otp = ? AND is_used = 0
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $expiredOtp = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($expiredOtp) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please check and try again.']);
        }
        exit;
    }
    
    // Mark OTP as used
    $stmt = $conn->prepare("UPDATE email_verifications SET is_used = 1 WHERE id = ?");
    $stmt->bind_param("i", $verification['id']);
    $stmt->execute();
    $stmt->close();
    
    // Mark user email as verified
    $stmt = $conn->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $verification['user_id']);
    $stmt->execute();
    $stmt->close();
    
    // Clear session verification data
    unset($_SESSION['pending_verification_user_id']);
    unset($_SESSION['pending_verification_email']);
    
    // Send welcome email
    MailHelper::sendWelcomeEmail($email, $verification['user_name']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Email verified successfully! You can now login.',
        'redirect' => (defined('BASE_PATH') ? BASE_PATH : '') . '/public/login.php'
    ]);
    
} catch (Exception $e) {
    error_log("Verify Email Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

