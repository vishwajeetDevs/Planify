<?php
/**
 * Get pending join requests for a board
 * GET /actions/share/requests.php?board_id=X
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$userId = $_SESSION['user_id'];
$boardId = intval($_GET['board_id'] ?? 0);

if ($boardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid board ID'], 400);
}

// Check if user has permission (must be owner, admin, or member)
// Also check if user is the board creator
$stmt = $conn->prepare("
    SELECT bm.role, b.created_by 
    FROM boards b
    LEFT JOIN board_members bm ON b.id = bm.board_id AND bm.user_id = ?
    WHERE b.id = ?
");
$stmt->bind_param("ii", $userId, $boardId);
$stmt->execute();
$access = $stmt->get_result()->fetch_assoc();

$hasAccess = false;
if ($access) {
    // User is board creator
    if ($access['created_by'] == $userId) {
        $hasAccess = true;
    }
    // User has owner, admin, or member role
    if ($access['role'] && in_array($access['role'], ['owner', 'admin', 'member'])) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    jsonResponse(['success' => false, 'message' => 'Only board owners and members can view join requests'], 403);
}

try {
    // Get all pending requests
    $stmt = $conn->prepare("
        SELECT jr.*, u.name as user_name, u.email as user_email, u.avatar as user_avatar
        FROM join_requests jr
        INNER JOIN users u ON jr.user_id = u.id
        WHERE jr.board_id = ? AND jr.status = 'pending'
        ORDER BY jr.created_at DESC
    ");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    jsonResponse([
        'success' => true,
        'requests' => $requests,
        'count' => count($requests)
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>

