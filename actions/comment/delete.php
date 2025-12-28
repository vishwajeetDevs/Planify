<?php
// Ensure no output before headers - clean any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Start session and include required files
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Set JSON header first, before any output
header('Content-Type: application/json; charset=utf-8');

global $conn;

// Ensure we have a valid database connection
if (!isset($conn) || !$conn) {
    if (ob_get_level()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    if (ob_get_level()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please log in'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get and validate input
$data = json_decode(file_get_contents('php://input'), true);
$comment_id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$comment_id) {
    http_response_code(400);
    if (ob_get_level()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid comment ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Get the comment to check ownership and get card_id for activity log
    $stmt = $conn->prepare("SELECT id, user_id, card_id FROM comments WHERE id = ?");
    $stmt->bind_param('i', $comment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        if (ob_get_level()) ob_clean();
        echo json_encode(['success' => false, 'message' => 'Comment not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $comment = $result->fetch_assoc();
    $card_id = $comment['card_id'];
    
    // Check if the current user is the author of the comment
    if ($comment['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        if (ob_get_level()) ob_clean();
        echo json_encode(['success' => false, 'message' => 'You are not authorized to delete this comment'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Get board_id for activity log
    $card_stmt = $conn->prepare("SELECT l.board_id FROM cards c INNER JOIN lists l ON c.list_id = l.id WHERE c.id = ?");
    $card_stmt->bind_param('i', $card_id);
    $card_stmt->execute();
    $card_result = $card_stmt->get_result()->fetch_assoc();
    $board_id = $card_result['board_id'];
    
    // Delete the comment
    $stmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->bind_param('i', $comment_id);
    $success = $stmt->execute();
    
    if ($success) {
        // Log activity - use correct column names: board_id, action (not type)
        $activity_desc = 'deleted a comment';
        $activity_action = 'comment';
        $activity_stmt = $conn->prepare("INSERT INTO activities (board_id, user_id, card_id, action, description) VALUES (?, ?, ?, ?, ?)");
        $activity_stmt->bind_param('iiiss', $board_id, $_SESSION['user_id'], $card_id, $activity_action, $activity_desc);
        $activity_stmt->execute();
        
        // Clean output buffer before sending JSON
        if (ob_get_level()) ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Comment deleted successfully'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        throw new Exception('Failed to delete comment from database');
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Comment deletion error: ' . $e->getMessage());
    
    // Clean any output before sending JSON
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
