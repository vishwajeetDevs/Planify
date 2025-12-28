<?php
/**
 * Leave Board API Endpoint
 * Allows a member (non-owner) to leave a board they belong to
 * 
 * POST /actions/board/leave.php
 * Body: { board_id: int }
 */

session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../helpers/IdEncrypt.php';

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
$userId = $_SESSION['user_id'];

// Validate board ID
if ($boardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid board ID'], 400);
}

try {
    // Check if user is a member of the board
    $stmt = $conn->prepare("
        SELECT bm.id, bm.role, b.name as board_name 
        FROM board_members bm
        INNER JOIN boards b ON bm.board_id = b.id
        WHERE bm.board_id = ? AND bm.user_id = ?
    ");
    $stmt->bind_param("ii", $boardId, $userId);
    $stmt->execute();
    $membership = $stmt->get_result()->fetch_assoc();
    
    if (!$membership) {
        jsonResponse(['success' => false, 'message' => 'You are not a member of this board'], 404);
    }
    
    // Check if user is the owner - owners cannot leave without transferring ownership
    if ($membership['role'] === 'owner') {
        jsonResponse([
            'success' => false, 
            'message' => 'As the board owner, you cannot leave. Please transfer ownership first or delete the board.'
        ], 403);
    }
    
    // Get user name for activity log
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $userName = $user['name'] ?? 'Unknown User';
    
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
    $stmt->bind_param("ii", $boardId, $userId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to remove membership');
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
    $stmt->bind_param("iii", $userId, $workspaceId, $userId);
    $stmt->execute();
    $otherBoardsAccess = $stmt->get_result()->fetch_assoc();
    
    // Also check if user is the workspace owner
    $stmt = $conn->prepare("SELECT owner_id FROM workspaces WHERE id = ?");
    $stmt->bind_param("i", $workspaceId);
    $stmt->execute();
    $workspaceInfo = $stmt->get_result()->fetch_assoc();
    $isWorkspaceOwner = ($workspaceInfo['owner_id'] == $userId);
    
    // If user has no access to any other boards and is not the workspace owner,
    // remove them from workspace_members as well
    $workspaceRemoved = false;
    if ($otherBoardsAccess['board_count'] == 0 && !$isWorkspaceOwner) {
        $stmt = $conn->prepare("DELETE FROM workspace_members WHERE workspace_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $workspaceId, $userId);
        $stmt->execute();
        $workspaceRemoved = true;
    }
    
    // Log activity
    logActivity($conn, $boardId, $userId, 'member_left', "$userName left the board");
    
    // Commit transaction
    $conn->commit();
    
    // Determine redirect URL
    // If workspace was removed, redirect to dashboard
    // Otherwise, redirect to workspace page
    $redirectUrl = $workspaceRemoved 
        ? '/public/dashboard.php' 
        : '/public/workspace.php?ref=' . encryptId($workspaceId);
    
    jsonResponse([
        'success' => true, 
        'message' => 'You have left the board successfully',
        'board_name' => $membership['board_name'],
        'redirect_url' => $redirectUrl,
        'workspace_removed' => $workspaceRemoved
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['success' => false, 'message' => 'Failed to leave board: ' . $e->getMessage()], 500);
}
?>

