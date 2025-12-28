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
$content = trim($data['content'] ?? '');

if (!$comment_id) {
    http_response_code(400);
    if (ob_get_level()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid comment ID'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($content)) {
    http_response_code(400);
    if (ob_get_level()) ob_clean();
    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Check if the comment exists and belongs to the current user
    $stmt = $conn->prepare("SELECT id, user_id FROM comments WHERE id = ?");
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
    
    // Check if the current user is the author of the comment
    if ($comment['user_id'] != $_SESSION['user_id']) {
        http_response_code(403);
        if (ob_get_level()) ob_clean();
        echo json_encode(['success' => false, 'message' => 'You are not authorized to edit this comment'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Update the comment
    $stmt = $conn->prepare("UPDATE comments SET content = ? WHERE id = ?");
    $stmt->bind_param('si', $content, $comment_id);
    $success = $stmt->execute();
    
    if ($success) {
        // Get card_id and board_id for activity log
        $card_stmt = $conn->prepare("SELECT l.board_id, cm.card_id FROM comments cm INNER JOIN cards ca ON cm.card_id = ca.id INNER JOIN lists l ON ca.list_id = l.id WHERE cm.id = ?");
        $card_stmt->bind_param('i', $comment_id);
        $card_stmt->execute();
        $card_result = $card_stmt->get_result()->fetch_assoc();
        $board_id = $card_result['board_id'];
        $card_id = $card_result['card_id'];
        
        // Log activity - use correct column names: board_id, action (not type)
        $activity_desc = 'updated a comment';
        $activity_action = 'comment';
        $activity_stmt = $conn->prepare("INSERT INTO activities (board_id, user_id, card_id, action, description) VALUES (?, ?, ?, ?, ?)");
        $activity_stmt->bind_param('iiiss', $board_id, $_SESSION['user_id'], $card_id, $activity_action, $activity_desc);
        $activity_stmt->execute();
        
        // Get the updated comment with user info for the response
        $comment_stmt = $conn->prepare("
            SELECT c.*, u.name as user_name, u.email as user_email 
            FROM comments c 
            LEFT JOIN users u ON c.user_id = u.id 
            WHERE c.id = ?"
        );
        $comment_stmt->bind_param('i', $comment_id);
        $comment_stmt->execute();
        $updated_comment = $comment_stmt->get_result()->fetch_assoc();
        
        // Clean output buffer before sending JSON
        if (ob_get_level()) ob_clean();
        echo json_encode([
            'success' => true, 
            'comment' => $updated_comment,
            'message' => 'Comment updated successfully'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        throw new Exception('Failed to update comment in database');
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Comment update error: ' . $e->getMessage());
    
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
