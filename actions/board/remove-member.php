<?php
/**
 * Remove Member API Endpoint
 * Allows the board owner to remove a member from the board
 * 
 * POST /actions/board/remove-member.php
 * Body: { board_id: int, user_id: int }
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
$targetUserId = intval($data['user_id'] ?? 0);
$currentUserId = $_SESSION['user_id'];

// Validate inputs
if ($boardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid board ID'], 400);
}

if ($targetUserId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid user ID'], 400);
}

// Cannot remove yourself using this endpoint
if ($targetUserId === $currentUserId) {
    jsonResponse([
        'success' => false, 
        'message' => 'You cannot remove yourself. Use "Leave Board" instead, or transfer ownership first.'
    ], 400);
}

try {
    // Check if current user is the board owner
    $stmt = $conn->prepare("
        SELECT bm.role, b.name as board_name 
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
    
    // Owner and admin can remove members
    if (!in_array($currentUserMembership['role'], ['owner', 'admin'])) {
        jsonResponse(['success' => false, 'message' => 'Only board owners and admins can remove members'], 403);
    }
    
    // Check if target user is a member of the board
    $stmt = $conn->prepare("
        SELECT bm.id, bm.role, u.name as user_name 
        FROM board_members bm
        INNER JOIN users u ON bm.user_id = u.id
        WHERE bm.board_id = ? AND bm.user_id = ?
    ");
    $stmt->bind_param("ii", $boardId, $targetUserId);
    $stmt->execute();
    $targetMembership = $stmt->get_result()->fetch_assoc();
    
    if (!$targetMembership) {
        jsonResponse(['success' => false, 'message' => 'User is not a member of this board'], 404);
    }
    
    // Cannot remove another owner (only one owner exists)
    if ($targetMembership['role'] === 'owner') {
        jsonResponse(['success' => false, 'message' => 'Cannot remove the board owner'], 403);
    }
    
    // Admins cannot remove other admins - only owner can
    if ($currentUserMembership['role'] === 'admin' && $targetMembership['role'] === 'admin') {
        jsonResponse(['success' => false, 'message' => 'Only the board owner can remove admins'], 403);
    }
    
    // Get current user name for activity log
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $currentUser = $stmt->get_result()->fetch_assoc();
    $currentUserName = $currentUser['name'] ?? 'Unknown User';
    
    // Get the workspace ID for this board
    $stmt = $conn->prepare("SELECT workspace_id FROM boards WHERE id = ?");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $boardInfo = $stmt->get_result()->fetch_assoc();
    $workspaceId = $boardInfo['workspace_id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Remove user from board
    $stmt = $conn->prepare("DELETE FROM board_members WHERE board_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $boardId, $targetUserId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to remove member');
    }
    
    // Check if user has access to any other boards in this workspace
    // They have access if they are a board member OR the board creator
    $stmt = $conn->prepare("
        SELECT COUNT(*) as board_count 
        FROM boards b
        LEFT JOIN board_members bm ON b.id = bm.board_id AND bm.user_id = ?
        WHERE b.workspace_id = ? 
        AND (bm.user_id IS NOT NULL OR b.created_by = ?)
    ");
    $stmt->bind_param("iii", $targetUserId, $workspaceId, $targetUserId);
    $stmt->execute();
    $otherBoardsAccess = $stmt->get_result()->fetch_assoc();
    
    // Also check if user is the workspace owner
    $stmt = $conn->prepare("SELECT owner_id FROM workspaces WHERE id = ?");
    $stmt->bind_param("i", $workspaceId);
    $stmt->execute();
    $workspaceInfo = $stmt->get_result()->fetch_assoc();
    $isWorkspaceOwner = ($workspaceInfo['owner_id'] == $targetUserId);
    
    // If user has no access to any other boards and is not the workspace owner,
    // remove them from workspace_members as well
    $workspaceRemoved = false;
    if ($otherBoardsAccess['board_count'] == 0 && !$isWorkspaceOwner) {
        $stmt = $conn->prepare("DELETE FROM workspace_members WHERE workspace_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $workspaceId, $targetUserId);
        $stmt->execute();
        $workspaceRemoved = true;
    }
    
    // Log activity
    logActivity($conn, $boardId, $currentUserId, 'member_removed', 
        "$currentUserName removed {$targetMembership['user_name']} from the board");
    
    // Create notification for removed user
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, data)
        VALUES (?, 'member_removed', 'Removed from Board', ?, ?)
    ");
    $notificationMessage = "You have been removed from the board \"{$currentUserMembership['board_name']}\" by $currentUserName";
    $notificationData = json_encode([
        'board_id' => $boardId,
        'board_name' => $currentUserMembership['board_name'],
        'removed_by' => $currentUserId,
        'workspace_removed' => $workspaceRemoved
    ]);
    $stmt->bind_param("iss", $targetUserId, $notificationMessage, $notificationData);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    jsonResponse([
        'success' => true, 
        'message' => "{$targetMembership['user_name']} has been removed from the board",
        'removed_user' => [
            'id' => $targetUserId,
            'name' => $targetMembership['user_name']
        ],
        'workspace_removed' => $workspaceRemoved
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['success' => false, 'message' => 'Failed to remove member: ' . $e->getMessage()], 500);
}
?>

