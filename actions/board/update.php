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
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$backgroundColor = $_POST['background_color'] ?? '#4F46E5';
$userId = $_SESSION['user_id'];

if (empty($name)) {
    jsonResponse(['success' => false, 'message' => 'Board name is required'], 400);
}

if ($boardId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid board'], 400);
}

try {
    // Check if user has permission to edit this board
    if (!canEditBoard($conn, $userId, $boardId)) {
        jsonResponse(['success' => false, 'message' => 'You do not have permission to edit this board'], 403);
    }

    // Update board
    $stmt = $conn->prepare("
        UPDATE boards 
        SET name = ?, description = ?, background_color = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("sssi", $name, $description, $backgroundColor, $boardId);
    
    if ($stmt->execute()) {
        jsonResponse([
            'success' => true,
            'message' => 'Board updated successfully',
            'board' => [
                'id' => $boardId,
                'name' => $name,
                'description' => $description,
                'background_color' => $backgroundColor
            ]
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update board'], 500);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
}
