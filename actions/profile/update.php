<?php
/**
 * Update User Profile
 * Handles name and email updates
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

// Validate CSRF token
validateCSRFToken();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$userId = $_SESSION['user_id'];

// Validate input
if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email is required']);
    exit;
}

try {
    // Check if email is already taken by another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email is already in use by another account']);
        exit;
    }
    
    // Update user profile
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $name, $email, $userId);
    
    if ($stmt->execute()) {
        // Update session data
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'name' => $name,
                'email' => $email
            ]
        ]);
    } else {
        throw new Exception('Failed to update profile');
    }
    
} catch (Exception $e) {
    error_log('Profile update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating your profile']);
}

