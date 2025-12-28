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

$input = json_decode(file_get_contents('php://input'), true);
$listId = intval($input['list_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($listId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid list'], 400);
}

try {
    // Get board ID and list title
    $stmt = $conn->prepare("SELECT board_id, title FROM lists WHERE id = ?");
    $stmt->bind_param("i", $listId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'List not found'], 404);
    }
    
    $list = $result->fetch_assoc();
    $boardId = $list['board_id'];
    $listTitle = $list['title'];
    
    // Check permission
    if (!canEditBoard($conn, $userId, $boardId)) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    
    // Delete list (cascades to cards)
    $stmt = $conn->prepare("DELETE FROM lists WHERE id = ?");
    $stmt->bind_param("i", $listId);
    
    if ($stmt->execute()) {
        // Log activity
        logActivity($conn, $boardId, $userId, 'list_deleted', "deleted list \"$listTitle\"");
        
        jsonResponse(['success' => true, 'message' => 'List deleted successfully']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to delete list'], 500);
    }
} catch (Exception $e) {
    secureErrorResponse($e, 'List delete');
}
?>