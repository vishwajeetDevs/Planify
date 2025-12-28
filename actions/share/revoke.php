<?php
/**
 * Revoke a share link
 * POST /actions/share/revoke.php
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

$shareLinkId = intval($input['share_link_id'] ?? 0);

if ($shareLinkId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid share link ID'], 400);
}

try {
    // Get share link details and verify ownership
    $stmt = $conn->prepare("
        SELECT sl.*, bm.role
        FROM share_links sl
        INNER JOIN board_members bm ON sl.board_id = bm.board_id AND bm.user_id = ?
        WHERE sl.id = ?
    ");
    $stmt->bind_param("ii", $userId, $shareLinkId);
    $stmt->execute();
    $shareLink = $stmt->get_result()->fetch_assoc();
    
    if (!$shareLink) {
        jsonResponse(['success' => false, 'message' => 'Share link not found'], 404);
    }
    
    // Owner, admin, or the link creator can revoke
    if (!in_array($shareLink['role'], ['owner', 'admin']) && $shareLink['owner_id'] !== $userId) {
        jsonResponse(['success' => false, 'message' => 'You do not have permission to revoke this link'], 403);
    }
    
    if ($shareLink['is_revoked']) {
        jsonResponse(['success' => false, 'message' => 'This link is already revoked'], 400);
    }
    
    // Revoke the link
    $stmt = $conn->prepare("
        UPDATE share_links 
        SET is_revoked = 1, revoked_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $shareLinkId);
    
    if ($stmt->execute()) {
        // Log activity
        logActivity($conn, $shareLink['board_id'], $userId, 'share_link_revoked', 'Revoked a share link');
        
        jsonResponse([
            'success' => true,
            'message' => 'Share link revoked successfully'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to revoke share link'], 500);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>

