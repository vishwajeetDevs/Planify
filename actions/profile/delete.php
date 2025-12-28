<?php
/**
 * Delete User Account
 * Permanently deletes user and all associated data
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

$userId = $_SESSION['user_id'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Delete user's comments
    $stmt = $conn->prepare("DELETE FROM comments WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Delete user's activities
    $stmt = $conn->prepare("DELETE FROM activities WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Delete user's attachments
    $stmt = $conn->prepare("DELETE FROM attachments WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Delete cards created by user
    $stmt = $conn->prepare("DELETE FROM cards WHERE created_by = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Delete board memberships
    $stmt = $conn->prepare("DELETE FROM board_members WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Delete workspace memberships
    $stmt = $conn->prepare("DELETE FROM workspace_members WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Get workspaces owned by user to delete their boards first
    $stmt = $conn->prepare("SELECT id FROM workspaces WHERE owner_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $workspaces = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($workspaces as $workspace) {
        // Delete boards in this workspace (cascade will handle lists, cards, etc.)
        $stmt = $conn->prepare("DELETE FROM boards WHERE workspace_id = ?");
        $stmt->bind_param("i", $workspace['id']);
        $stmt->execute();
    }
    
    // Delete workspaces owned by user
    $stmt = $conn->prepare("DELETE FROM workspaces WHERE owner_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    // Finally, delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    
    $conn->commit();
    
    // Destroy session
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Account deleted successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log('Account deletion error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while deleting your account']);
}

