<?php
/**
 * Get Activity API
 */
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

require_once '../../config/db.php';
require_once '../../includes/functions.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

global $conn;

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$card_id = filter_input(INPUT_GET, 'card_id', FILTER_VALIDATE_INT);

if (!$card_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid card ID']);
    exit;
}

try {
    $result = $conn->query("SHOW TABLES LIKE 'activities'");
    if (!$result || $result->num_rows === 0) {
        echo json_encode(['success' => true, 'activities' => []]);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT l.board_id FROM cards c JOIN lists l ON c.list_id = l.id WHERE c.id = ?");
    $stmt->bind_param('i', $card_id);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    
    if (!$card) {
        echo json_encode(['success' => false, 'message' => 'Card not found']);
        exit;
    }
    
    // Check if user has access to this board
    if (!hasAccessToBoard($conn, $_SESSION['user_id'], $card['board_id'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    // Get activities for this specific card only (not board-level activities)
    $stmt = $conn->prepare("SELECT a.*, u.name as user_name, u.email as user_email FROM activities a LEFT JOIN users u ON a.user_id = u.id WHERE a.card_id = ? ORDER BY a.created_at DESC LIMIT 50");
    $stmt->bind_param('i', $card_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = [];
    
    while ($row = $result->fetch_assoc()) {
        if (empty($row['user_name'])) $row['user_name'] = 'System';
        $activities[] = $row;
    }
    
    echo json_encode(['success' => true, 'activities' => $activities]);
    
} catch (Exception $e) {
    error_log('Error in activity/get.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
