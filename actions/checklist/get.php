<?php
/**
 * Get Checklists API
 */
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$cardId = isset($_GET['card_id']) ? (int)$_GET['card_id'] : 0;

if (!$cardId) {
    echo json_encode(['success' => false, 'message' => 'Card ID required']);
    exit;
}

try {
    // Get card's board_id for access check
    $stmt = $conn->prepare("SELECT l.board_id FROM cards c JOIN lists l ON c.list_id = l.id WHERE c.id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('i', $cardId);
    $stmt->execute();
    $result = $stmt->get_result();
    $card = $result->fetch_assoc();
    $stmt->close();
    
    if (!$card) {
        echo json_encode(['success' => false, 'message' => 'Card not found']);
        exit;
    }
    
    if (!hasAccessToBoard($conn, $_SESSION['user_id'], $card['board_id'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    // Check if checklists table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'checklists'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        echo json_encode(['success' => true, 'checklists' => []]);
        exit;
    }

    // Get checklists
    $stmt = $conn->prepare("SELECT * FROM checklists WHERE card_id = ? ORDER BY position, id");
    if (!$stmt) {
        echo json_encode(['success' => true, 'checklists' => []]);
        exit;
    }
    $stmt->bind_param('i', $cardId);
    $stmt->execute();
    $result = $stmt->get_result();
    $checklists = [];
    
    while ($checklist = $result->fetch_assoc()) {
        $checklistId = (int)$checklist['id'];
        $checklist['id'] = $checklistId;
        $checklist['card_id'] = (int)$checklist['card_id'];
        
        // Get items for this checklist
        $itemStmt = $conn->prepare("SELECT * FROM checklist_items WHERE checklist_id = ? ORDER BY position, id");
        if ($itemStmt) {
            $itemStmt->bind_param('i', $checklistId);
            $itemStmt->execute();
            $itemResult = $itemStmt->get_result();
            
            $items = [];
            $completed = 0;
            while ($item = $itemResult->fetch_assoc()) {
                $item['id'] = (int)$item['id'];
                $item['checklist_id'] = (int)$item['checklist_id'];
                $item['is_completed'] = (bool)$item['is_completed'];
                if ($item['is_completed']) $completed++;
                $items[] = $item;
            }
            $itemStmt->close();
            
            $checklist['items'] = $items;
            $checklist['total'] = count($items);
            $checklist['completed'] = $completed;
        } else {
            $checklist['items'] = [];
            $checklist['total'] = 0;
            $checklist['completed'] = 0;
        }
        
        $checklists[] = $checklist;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'checklists' => $checklists]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
