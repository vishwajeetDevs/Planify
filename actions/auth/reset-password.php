<?php
/**
 * Reset Password
 * 
 * Validates token and updates user password
 */

session_start();
header('Content-Type: application/json');

require_once '../../config/db.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$email = trim(strtolower($input['email'] ?? ''));
$password = $input['password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

// Validate inputs
if (!$token || !$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid reset link']);
    exit;
}

if (!$password || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

if ($password !== $confirmPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

try {
    // Find valid reset token
    $stmt = $conn->prepare("
        SELECT pr.*, u.name as user_name 
        FROM password_resets pr
        INNER JOIN users u ON pr.user_id = u.id
        WHERE pr.email = ? 
        AND pr.token = ? 
        AND pr.is_used = 0 
        AND pr.expires_at > NOW()
        ORDER BY pr.created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$reset) {
        // Check if token exists but expired
        $stmt = $conn->prepare("
            SELECT * FROM password_resets 
            WHERE email = ? AND token = ? AND is_used = 0
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $expiredToken = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($expiredToken) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Reset link has expired. Please request a new one.']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid reset link.']);
        }
        exit;
    }
    
    // Mark token as used
    $stmt = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE id = ?");
    $stmt->bind_param("i", $reset['id']);
    $stmt->execute();
    $stmt->close();
    
    // Update user password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $reset['user_id']);
    $stmt->execute();
    $stmt->close();
    
    // Invalidate all other reset tokens for this user
    $stmt = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $reset['user_id']);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Password reset successfully! You can now login with your new password.',
        'redirect' => (defined('BASE_PATH') ? BASE_PATH : '') . '/public/login.php'
    ]);
    
} catch (Exception $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

