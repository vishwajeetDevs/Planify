<?php
// Clean output buffer
while (ob_get_level()) {
    ob_end_clean();
}

session_start();
require_once '../../includes/functions.php';
require_once '../../config/db.php';

// Set JSON header early
header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'redirect' => (defined('BASE_PATH') ? BASE_PATH : '') . '/public/login.php']);
    exit;
}

// Validate card ID
$cardId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$cardId || $cardId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid card ID']);
    exit;
}

try {
    // Get card details with list name
    $stmt = $conn->prepare("
        SELECT c.*, u.name as created_by_name, l.title as list_name
        FROM cards c
        INNER JOIN users u ON c.created_by = u.id
        INNER JOIN lists l ON c.list_id = l.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $cardId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Card not found']);
        exit;
    }

    $card = $result->fetch_assoc();

    // Security: Check if user has access to this card's board
    $boardStmt = $conn->prepare("SELECT board_id FROM lists WHERE id = ?");
    $boardStmt->bind_param("i", $card['list_id']);
    $boardStmt->execute();
    $boardResult = $boardStmt->get_result()->fetch_assoc();
    
    if (!$boardResult || !hasAccessToBoard($conn, $_SESSION['user_id'], $boardResult['board_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    // Get checklist progress
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM checklist_items ci 
             INNER JOIN checklists ch ON ci.checklist_id = ch.id 
             WHERE ch.card_id = ?) as total,
            (SELECT COUNT(*) FROM checklist_items ci 
             INNER JOIN checklists ch ON ci.checklist_id = ch.id 
             WHERE ch.card_id = ? AND ci.is_completed = 1) as completed
    ");
    $stmt->bind_param("ii", $cardId, $cardId);
    $stmt->execute();
    $checklistResult = $stmt->get_result()->fetch_assoc();

    $card['checklist_total'] = $checklistResult['total'] ?? 0;
    $card['checklist_completed'] = $checklistResult['completed'] ?? 0;

    // Get comment count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM comments WHERE card_id = ?");
    $stmt->bind_param("i", $cardId);
    $stmt->execute();
    $commentCount = $stmt->get_result()->fetch_assoc()['count'];
    $card['comment_count'] = $commentCount;

    // Get attachment count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM attachments WHERE card_id = ?");
    $stmt->bind_param("i", $cardId);
    $stmt->execute();
    $attachmentCount = $stmt->get_result()->fetch_assoc()['count'];
    $card['attachment_count'] = $attachmentCount;

    echo json_encode([
        'success' => true,
        'card' => $card
    ]);
    
} catch (Exception $e) {
    error_log('Card get error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while loading the card'
    ]);
}
?>
