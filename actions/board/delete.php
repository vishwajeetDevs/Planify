<?php
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

$boardId = intval($_POST['board_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($boardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid board'], 400);
}

try {
    $stmt = $conn->prepare("SELECT id, workspace_id FROM boards WHERE id = ?");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $board = $stmt->get_result()->fetch_assoc();

    if (!$board) {
        jsonResponse(['success' => false, 'message' => 'Board not found'], 404);
    }

    if (!canManageBoard($conn, $userId, $boardId)) {
        jsonResponse(['success' => false, 'message' => 'Only an Admin or Super Admin can delete this board'], 403);
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Delete all related data (lists, cards, comments, etc.)
        // Note: Make sure you have appropriate foreign key constraints with CASCADE DELETE
        // or delete related records manually in the correct order

        // Delete board members
        $stmt = $conn->prepare("DELETE FROM board_members WHERE board_id = ?");
        $stmt->bind_param("i", $boardId);
        $stmt->execute();

        // Delete the board
        $stmt = $conn->prepare("DELETE FROM boards WHERE id = ?");
        $stmt->bind_param("i", $boardId);
        $stmt->execute();

        $conn->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Board deleted successfully',
            'workspace_id' => $board['workspace_id']
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    secureErrorResponse($e, 'Board delete');
}
