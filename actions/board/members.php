<?php
/**
 * Get Board Members API
 * Returns list of all members for a board (for @mention autocomplete)
 */

// Ensure no output before headers
while (ob_get_level()) {
    ob_end_clean();
}

session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

global $conn;

// Check database connection
if (!isset($conn) || !$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get board ID
$board_id = filter_input(INPUT_GET, 'board_id', FILTER_VALIDATE_INT);

if (!$board_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid board ID']);
    exit;
}

try {
    // Verify user has access to this board
    $accessStmt = $conn->prepare("
        SELECT role FROM board_members 
        WHERE board_id = ? AND user_id = ?
    ");
    $accessStmt->bind_param('ii', $board_id, $_SESSION['user_id']);
    $accessStmt->execute();
    $accessResult = $accessStmt->get_result();
    
    if ($accessResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    // Get all board members with their details
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.avatar,
            bm.role
        FROM board_members bm
        INNER JOIN users u ON bm.user_id = u.id
        WHERE bm.board_id = ?
        ORDER BY 
            CASE bm.role 
                WHEN 'owner' THEN 1 
                WHEN 'admin' THEN 2 
                WHEN 'member' THEN 3 
                WHEN 'commenter' THEN 4
                WHEN 'viewer' THEN 5 
            END,
            u.name ASC
    ");
    $stmt->bind_param('i', $board_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'avatar' => $row['avatar'],
            'role' => $row['role']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'members' => $members,
        'count' => count($members)
    ]);
    
} catch (Exception $e) {
    error_log('Error in board/members.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error'
    ]);
}

