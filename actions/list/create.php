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
$title = trim($_POST['title'] ?? '');
$userId = $_SESSION['user_id'];

if (empty($title)) {
    jsonResponse(['success' => false, 'message' => 'List title is required'], 400);
}

// Validate title length
if (mb_strlen($title) > 100) {
    jsonResponse(['success' => false, 'message' => 'List title must be 100 characters or less'], 400);
}

if ($boardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid board'], 400);
}

try {
    // Check permission
    if (!canEditBoard($conn, $userId, $boardId)) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    
    // Get max position
    $stmt = $conn->prepare("SELECT MAX(position) as max_pos FROM lists WHERE board_id = ?");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $maxPos = $stmt->get_result()->fetch_assoc()['max_pos'] ?? 0;
    $newPosition = $maxPos + 1;
    
    // Insert list
    $stmt = $conn->prepare("INSERT INTO lists (board_id, title, position) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $boardId, $title, $newPosition);
    
    if ($stmt->execute()) {
        $listId = $conn->insert_id;
        
        // Log activity
        logActivity($conn, $boardId, $userId, 'list_created', "created list \"$title\"");
        
        jsonResponse([
            'success' => true,
            'message' => 'List created successfully',
            'list_id' => $listId
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to create list'], 500);
    }
} catch (Exception $e) {
    secureErrorResponse($e, 'List creation');
}
?>