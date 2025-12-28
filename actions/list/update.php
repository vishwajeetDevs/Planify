<?php
// Ensure no output before headers
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Ensure user is logged in
requireLogin();

// Validate CSRF token
validateCSRFToken();

// Get the raw POST data
$input = json_decode(file_get_contents('php://input'), true);
$listId = $input['id'] ?? null;
$title = trim($input['title'] ?? '');
$boardId = $input['board_id'] ?? null;

// Validate input
if (empty($listId) || empty($title) || empty($boardId)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    // Check if the list exists and belongs to the specified board
    $stmt = $conn->prepare("
        SELECT id 
        FROM lists 
        WHERE id = ? AND board_id = ?
    ");
    $stmt->bind_param("ii", $listId, $boardId);
    $stmt->execute();
    $result = $list = $stmt->get_result()->fetch_assoc();
    
    if (!$list) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'List not found or access denied']);
        exit;
    }
    
    // Check if user can edit the board (owner, admin, or member)
    if (!canEditBoard($conn, $_SESSION['user_id'], $boardId)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this list']);
        exit;
    }
    
    // Update the list title
    $stmt = $conn->prepare("
        UPDATE lists 
        SET title = ?, updated_at = NOW()
        WHERE id = ? AND board_id = ?
    ");
    $stmt->bind_param("sii", $title, $listId, $boardId);
    
    if ($stmt->execute()) {
        // Log the activity
        logActivity($conn, $boardId, $_SESSION['user_id'], 'list_updated', "updated list '$title'");
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'List updated successfully',
            'list' => [
                'id' => $listId,
                'title' => $title
            ]
        ]);
    } else {
        throw new Exception('Failed to update list in database');
    }
    
} catch (Exception $e) {
    error_log('Error updating list: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    // Hide detailed errors in production
    $message = (class_exists('Env') && Env::isDevelopment()) 
        ? 'An error occurred while updating the list: ' . $e->getMessage()
        : 'A server error occurred. Please try again later.';
    echo json_encode([
        'success' => false, 
        'message' => $message
    ]);
}

$conn->close();
?>
