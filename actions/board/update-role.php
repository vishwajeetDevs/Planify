<?php
/**
 * Change a board member's role.
 * Super Admin can promote a Member to Admin and demote an Admin to Member.
 * Admin can promote a Member to Admin only.
 * Nobody can change the Super Admin role here.
 *
 * POST /actions/board/update-role.php
 * Body: { board_id: int, user_id: int, role: "admin"|"member" }
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

validateCSRFToken();

$data = getRequestData();
$boardId = intval($data['board_id'] ?? 0);
$targetUserId = intval($data['user_id'] ?? 0);
$nextRole = $data['role'] ?? '';
$currentUserId = (int) $_SESSION['user_id'];

if ($boardId <= 0 || $targetUserId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid member'], 400);
}

if (!in_array($nextRole, ['admin', 'member'], true)) {
    jsonResponse(['success' => false, 'message' => 'Invalid role'], 400);
}

if ($targetUserId === $currentUserId) {
    jsonResponse(['success' => false, 'message' => 'You cannot change your own role'], 400);
}

try {
    $stmt = $conn->prepare("
        SELECT role FROM board_members
        WHERE board_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $boardId, $currentUserId);
    $stmt->execute();
    $actor = $stmt->get_result()->fetch_assoc();

    if (!$actor || !in_array($actor['role'], ['owner', 'admin'], true)) {
        jsonResponse(['success' => false, 'message' => 'Only a Super Admin or Admin can change roles'], 403);
    }

    $stmt = $conn->prepare("
        SELECT bm.role, u.name
        FROM board_members bm
        INNER JOIN users u ON u.id = bm.user_id
        WHERE bm.board_id = ? AND bm.user_id = ?
    ");
    $stmt->bind_param("ii", $boardId, $targetUserId);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();

    if (!$target) {
        jsonResponse(['success' => false, 'message' => 'That person is not on this board'], 404);
    }

    if ($target['role'] === 'owner') {
        jsonResponse(['success' => false, 'message' => 'The Super Admin role can only be transferred'], 403);
    }

    if ($target['role'] === $nextRole) {
        jsonResponse(['success' => true, 'message' => $target['name'] . ' is already ' . roleLabel($nextRole)]);
    }

    $promoting = $target['role'] === 'member' && $nextRole === 'admin';
    $demoting = $target['role'] === 'admin' && $nextRole === 'member';

    if (!$promoting && !$demoting) {
        jsonResponse(['success' => false, 'message' => 'That role change is not allowed'], 400);
    }

    if ($actor['role'] === 'admin' && !$promoting) {
        jsonResponse(['success' => false, 'message' => 'Only the Super Admin can demote an Admin'], 403);
    }

    $stmt = $conn->prepare("UPDATE board_members SET role = ? WHERE board_id = ? AND user_id = ? AND role <> 'owner'");
    $stmt->bind_param("sii", $nextRole, $boardId, $targetUserId);
    if (!$stmt->execute() || $stmt->affected_rows < 1) {
        throw new Exception('Could not update the role');
    }

    $verb = $promoting ? 'promoted' : 'demoted';
    logActivity($conn, $boardId, $currentUserId, 'role_changed', $target['name'] . ' was ' . $verb . ' to ' . roleLabel($nextRole));

    jsonResponse([
        'success' => true,
        'message' => $target['name'] . ' is now ' . roleLabel($nextRole),
        'role' => $nextRole,
        'role_label' => roleLabel($nextRole)
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Could not update the role'], 500);
}
