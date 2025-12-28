<?php
/**
 * Get share links for a board (owner only)
 * GET /actions/share/get.php?board_id=X
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

// Check if user has permission to view share links (must be owner or member)
$stmt = $conn->prepare("
    SELECT role FROM board_members 
    WHERE board_id = ? AND user_id = ?
");
$stmt->bind_param("ii", $boardId, $userId);
$stmt->execute();
$access = $stmt->get_result()->fetch_assoc();

if (!$access || !in_array($access['role'], ['owner', 'admin', 'member'])) {
    jsonResponse(['success' => false, 'message' => 'You do not have permission to view share links'], 403);
}

try {
    // Get all share links for this board
    $stmt = $conn->prepare("
        SELECT sl.*, u.name as owner_name,
               (SELECT COUNT(*) FROM share_link_uses WHERE share_link_id = sl.id AND action = 'joined') as join_count
        FROM share_links sl
        INNER JOIN users u ON sl.owner_id = u.id
        WHERE sl.board_id = ?
        ORDER BY sl.created_at DESC
    ");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $shareLinks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Process each link to determine status
    foreach ($shareLinks as &$link) {
        // Determine link status
        if ($link['is_revoked']) {
            $link['status'] = 'revoked';
        } elseif ($link['expires_at'] && strtotime($link['expires_at']) < time()) {
            $link['status'] = 'expired';
        } elseif ($link['max_uses'] && $link['uses'] >= $link['max_uses']) {
            $link['status'] = 'exhausted';
        } elseif ($link['single_use'] && $link['uses'] > 0) {
            $link['status'] = 'used';
        } else {
            $link['status'] = 'active';
        }
        
        // Remove sensitive data
        unset($link['token_hash']);
    }
    
    jsonResponse([
        'success' => true,
        'share_links' => $shareLinks
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
?>

