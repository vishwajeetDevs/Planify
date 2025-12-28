<?php
/**
 * Get Board Content API
 * Returns lists and cards for a board without full page data
 * Used for refreshing board content without full page reload
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$boardId = filter_input(INPUT_GET, 'board_id', FILTER_VALIDATE_INT);

if (!$boardId) {
    echo json_encode(['success' => false, 'message' => 'Board ID required']);
    exit;
}

$userId = $_SESSION['user_id'];

// Check access
if (!hasAccessToBoard($conn, $userId, $boardId)) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    // Get lists
    $stmt = $conn->prepare("
        SELECT id, title, position 
        FROM lists 
        WHERE board_id = ? 
        ORDER BY position ASC
    ");
    $stmt->bind_param("i", $boardId);
    $stmt->execute();
    $listsResult = $stmt->get_result();
    
    $lists = [];
    while ($list = $listsResult->fetch_assoc()) {
        $list['cards'] = [];
        $lists[$list['id']] = $list;
    }
    
    // Get all cards for these lists in one query
    if (!empty($lists)) {
        $listIds = array_keys($lists);
        $placeholders = implode(',', array_fill(0, count($listIds), '?'));
        $types = str_repeat('i', count($listIds));
        
        $stmt = $conn->prepare("
            SELECT c.id, c.list_id, c.title, c.description, c.position, c.is_completed,
                   c.start_date, c.due_date, c.priority
            FROM cards c
            WHERE c.list_id IN ($placeholders)
            ORDER BY c.position ASC
        ");
        $stmt->bind_param($types, ...$listIds);
        $stmt->execute();
        $cardsResult = $stmt->get_result();
        
        while ($card = $cardsResult->fetch_assoc()) {
            $card['id'] = (int)$card['id'];
            $card['list_id'] = (int)$card['list_id'];
            $card['is_completed'] = (bool)$card['is_completed'];
            $lists[$card['list_id']]['cards'][] = $card;
        }
    }
    
    echo json_encode([
        'success' => true,
        'lists' => array_values($lists)
    ]);
    
} catch (Exception $e) {
    error_log('Error in get-content.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

