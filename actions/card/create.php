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

$listId = intval($_POST['list_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$userId = $_SESSION['user_id'];

if (empty($title)) {
    jsonResponse(['success' => false, 'message' => 'Card title is required'], 400);
}

if ($listId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid list'], 400);
}

try {
    // Get board ID from list
    $stmt = $conn->prepare("SELECT board_id FROM lists WHERE id = ?");
    $stmt->bind_param("i", $listId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'List not found'], 404);
    }
    
    $list = $result->fetch_assoc();
    $boardId = $list['board_id'];
    
    // Check if user can edit board
    if (!canEditBoard($conn, $userId, $boardId)) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    
    // Get max position
    $stmt = $conn->prepare("SELECT MAX(position) as max_pos FROM cards WHERE list_id = ?");
    $stmt->bind_param("i", $listId);
    $stmt->execute();
    $maxPos = $stmt->get_result()->fetch_assoc()['max_pos'] ?? 0;
    $newPosition = $maxPos + 1;
    
    // Insert card
    $stmt = $conn->prepare("
        INSERT INTO cards (list_id, title, description, position, created_by) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issii", $listId, $title, $description, $newPosition, $userId);
    
    if ($stmt->execute()) {
        $cardId = $conn->insert_id;
        
        // Log activity
        logActivity($conn, $boardId, $userId, 'card_created', "created card \"$title\"", $cardId);
        
        // Get creator name for response
        $creatorName = $_SESSION['user_name'] ?? 'Unknown';
        if (empty($creatorName) || $creatorName === 'Unknown') {
            $userStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $userStmt->bind_param("i", $userId);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            if ($userRow = $userResult->fetch_assoc()) {
                $creatorName = $userRow['name'];
            }
        }
        
        // Return full card data for optimistic UI
        jsonResponse([
            'success' => true,
            'message' => 'Card created successfully',
            'card_id' => $cardId,
            'card' => [
                'id' => $cardId,
                'list_id' => $listId,
                'title' => $title,
                'description' => $description,
                'position' => $newPosition,
                'is_completed' => false,
                'created_by' => $userId,
                'created_by_name' => $creatorName
            ]
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to create card'], 500);
    }
} catch (Exception $e) {
    secureErrorResponse($e, 'Card creation');
}
?>