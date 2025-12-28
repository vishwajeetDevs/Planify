<?php
/**
 * Create Checklist API
 */
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

require_once '../../config/db.php';
require_once '../../includes/functions.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$cardId = filter_var($data['card_id'] ?? 0, FILTER_VALIDATE_INT);
$title = trim($data['title'] ?? 'Checklist');

if (!$cardId) {
    echo json_encode(['success' => false, 'message' => 'Card ID required']);
    exit;
}

global $conn;

try {
    $stmt = $conn->prepare("SELECT l.board_id FROM cards c JOIN lists l ON c.list_id = l.id WHERE c.id = ?");
    $stmt->bind_param('i', $cardId);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    
    if (!$card || !hasAccessToBoard($conn, $_SESSION['user_id'], $card['board_id'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    $stmt = $conn->prepare("SELECT COALESCE(MAX(position), 0) + 1 as pos FROM checklists WHERE card_id = ?");
    $stmt->bind_param('i', $cardId);
    $stmt->execute();
    $pos = $stmt->get_result()->fetch_assoc()['pos'];

    $stmt = $conn->prepare("INSERT INTO checklists (card_id, title, position) VALUES (?, ?, ?)");
    $stmt->bind_param('isi', $cardId, $title, $pos);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Checklist created',
            'checklist' => [
                'id' => $conn->insert_id,
                'card_id' => $cardId,
                'title' => $title,
                'position' => $pos,
                'items' => [],
                'total' => 0,
                'completed' => 0
            ]
        ]);
    } else {
        throw new Exception('Failed to create checklist');
    }

} catch (Exception $e) {
    error_log('Error in checklist/create.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to create checklist']);
}
