<?php
/**
 * Change User Password
 * Handles password updates with verification
 */

// Clean output buffer
while (ob_get_level()) {
    ob_end_clean();
}

session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$currentPassword = $input['current_password'] ?? '';
$newPassword = $input['new_password'] ?? '';
$userId = $_SESSION['user_id'];

// Validate input
if (empty($currentPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Current password is required']);
    exit;
}

if (empty($newPassword)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password is required']);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
    exit;
}

try {
    // Get current user's password hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update password');
    }
    
} catch (Exception $e) {
    error_log('Password change error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating your password']);
}

