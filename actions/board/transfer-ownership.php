<?php
/**
 * Transfer Board Ownership API Endpoint
 * Allows the board owner to transfer ownership to another member
 * 
 * POST /actions/board/transfer-ownership.php
 * Body: { board_id: int, new_owner_id: int }
 */

session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Require authentication
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 400);
}

// Validate CSRF token
validateCSRFToken();

$data = getRequestData();
$boardId = intval($data['board_id'] ?? 0);
$newOwnerId = intval($data['new_owner_id'] ?? 0);
$currentUserId = $_SESSION['user_id'];

// Validate inputs
if ($boardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid board ID'], 400);
}

if ($newOwnerId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid new owner ID'], 400);
}

// Cannot transfer to yourself
if ($newOwnerId === $currentUserId) {
    jsonResponse(['success' => false, 'message' => 'You are already the owner'], 400);
}

try {
    // Check if current user is the board owner
    $stmt = $conn->prepare("
        SELECT bm.role, b.name as board_name, b.id as board_id
        FROM board_members bm
        INNER JOIN boards b ON bm.board_id = b.id
        WHERE bm.board_id = ? AND bm.user_id = ?
    ");
    $stmt->bind_param("ii", $boardId, $currentUserId);
    $stmt->execute();
    $currentUserMembership = $stmt->get_result()->fetch_assoc();
    
    if (!$currentUserMembership) {
        jsonResponse(['success' => false, 'message' => 'You are not a member of this board'], 403);
    }
    
    if ($currentUserMembership['role'] !== 'owner') {
        jsonResponse(['success' => false, 'message' => 'Only the current owner can transfer ownership'], 403);
    }
    
    // Check if new owner is a member of the board
    $stmt = $conn->prepare("
        SELECT bm.id, bm.role, u.name as user_name, u.email
        FROM board_members bm
        INNER JOIN users u ON bm.user_id = u.id
        WHERE bm.board_id = ? AND bm.user_id = ?
    ");
    $stmt->bind_param("ii", $boardId, $newOwnerId);
    $stmt->execute();
    $newOwnerMembership = $stmt->get_result()->fetch_assoc();
    
    if (!$newOwnerMembership) {
        jsonResponse(['success' => false, 'message' => 'The selected user is not a member of this board'], 404);
    }
    
    // Get current user name for activity log
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $currentUser = $stmt->get_result()->fetch_assoc();
    $currentUserName = $currentUser['name'] ?? 'Unknown User';
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Update current owner to member
    $stmt = $conn->prepare("UPDATE board_members SET role = 'member' WHERE board_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $boardId, $currentUserId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update current owner role');
    }
    
    // Update new owner role
    $stmt = $conn->prepare("UPDATE board_members SET role = 'owner' WHERE board_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $boardId, $newOwnerId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to update new owner role');
    }
    
    // Update boards table created_by (optional - for consistency)
    $stmt = $conn->prepare("UPDATE boards SET created_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $newOwnerId, $boardId);
    $stmt->execute();
    
    // Log activity
    logActivity($conn, $boardId, $currentUserId, 'ownership_transferred', 
        "$currentUserName transferred board ownership to {$newOwnerMembership['user_name']}");
    
    // Create notification for new owner
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, data)
        VALUES (?, 'ownership_received', 'Board Ownership Transferred', ?, ?)
    ");
    $notificationMessage = "$currentUserName has transferred ownership of \"{$currentUserMembership['board_name']}\" to you";
    $notificationData = json_encode([
        'board_id' => $boardId,
        'board_name' => $currentUserMembership['board_name'],
        'previous_owner' => $currentUserId
    ]);
    $stmt->bind_param("iss", $newOwnerId, $notificationMessage, $notificationData);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    jsonResponse([
        'success' => true, 
        'message' => "Ownership transferred to {$newOwnerMembership['user_name']} successfully",
        'new_owner' => [
            'id' => $newOwnerId,
            'name' => $newOwnerMembership['user_name'],
            'email' => $newOwnerMembership['email']
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['success' => false, 'message' => 'Failed to transfer ownership: ' . $e->getMessage()], 500);
}
?>

