<?php
/**
 * Handle join requests (approve/reject)
 * POST /actions/share/request.php
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 400);
}

// Validate CSRF token
validateCSRFToken();

$userId = $_SESSION['user_id'];

// Parse JSON input if content type is JSON
$input = [];
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $input = $_POST;
}

$requestId = intval($input['request_id'] ?? 0);
$action = $input['action'] ?? ''; // 'approve' or 'reject'

if ($requestId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid request ID'], 400);
}

if (!in_array($action, ['approve', 'reject'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

try {
    // Get request details and verify ownership
    $stmt = $conn->prepare("
        SELECT jr.*, sl.role_on_join, b.name as board_name, b.workspace_id,
               bm.role as handler_role, u.name as requester_name
        FROM join_requests jr
        INNER JOIN share_links sl ON jr.share_link_id = sl.id
        INNER JOIN boards b ON jr.board_id = b.id
        INNER JOIN board_members bm ON b.id = bm.board_id AND bm.user_id = ?
        INNER JOIN users u ON jr.user_id = u.id
        WHERE jr.id = ?
    ");
    $stmt->bind_param("ii", $userId, $requestId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    
    if (!$request) {
        jsonResponse(['success' => false, 'message' => 'Request not found or you do not have permission'], 404);
    }
    
    // Only owner or admin can handle requests
    if (!in_array($request['handler_role'], ['owner', 'admin'])) {
        jsonResponse(['success' => false, 'message' => 'Only board owners and admins can handle join requests'], 403);
    }
    
    if ($request['status'] !== 'pending') {
        jsonResponse(['success' => false, 'message' => 'This request has already been handled'], 400);
    }
    
    $conn->begin_transaction();
    
    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    
    // Update request status
    $stmt = $conn->prepare("
        UPDATE join_requests 
        SET status = ?, handled_by = ?, handled_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $newStatus, $userId, $requestId);
    $stmt->execute();
    
    if ($action === 'approve') {
        // Add user as board member only if they're not already a member
        // This prevents downgrading existing members
        $role = $request['role_on_join'];
        $stmt = $conn->prepare("
            INSERT IGNORE INTO board_members (board_id, user_id, role)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iis", $request['board_id'], $request['user_id'], $role);
        $stmt->execute();
        
        // Add to workspace members if not already
        $stmt = $conn->prepare("
            INSERT IGNORE INTO workspace_members (workspace_id, user_id, role)
            VALUES (?, ?, 'viewer')
        ");
        $stmt->bind_param("ii", $request['workspace_id'], $request['user_id']);
        $stmt->execute();
        
        // Log activity
        logActivity($conn, $request['board_id'], $request['user_id'], 'joined_board', 'Joined the board (request approved)');
        
        // Notify the requester
        $notificationData = json_encode([
            'board_id' => $request['board_id'],
            'role' => $role
        ]);
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, data)
            VALUES (?, 'request_approved', 'Request Approved', ?, ?)
        ");
        $message = 'Your request to join "' . $request['board_name'] . '" has been approved!';
        $stmt->bind_param("iss", $request['user_id'], $message, $notificationData);
        $stmt->execute();
    } else {
        // Notify the requester of rejection
        $notificationData = json_encode([
            'board_id' => $request['board_id']
        ]);
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, data)
            VALUES (?, 'request_rejected', 'Request Declined', ?, ?)
        ");
        $message = 'Your request to join "' . $request['board_name'] . '" was not approved.';
        $stmt->bind_param("iss", $request['user_id'], $message, $notificationData);
        $stmt->execute();
    }
    
    $conn->commit();
    
    jsonResponse([
        'success' => true,
        'message' => $action === 'approve' 
            ? $request['requester_name'] . ' has been added to the board'
            : 'Request has been declined'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>

