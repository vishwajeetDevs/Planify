<?php
/**
 * Toggle Label on Card API
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
$labelId = filter_var($data['label_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$cardId || !$labelId) {
    echo json_encode(['success' => false, 'message' => 'Card ID and Label ID required']);
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

    $stmt = $conn->prepare("SELECT id FROM card_labels WHERE card_id = ? AND label_id = ?");
    $stmt->bind_param('ii', $cardId, $labelId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $stmt = $conn->prepare("DELETE FROM card_labels WHERE card_id = ? AND label_id = ?");
        $stmt->bind_param('ii', $cardId, $labelId);
        $stmt->execute();
        $action = 'removed';
    } else {
        $stmt = $conn->prepare("INSERT INTO card_labels (card_id, label_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $cardId, $labelId);
        $stmt->execute();
        $action = 'added';
    }

    $stmt = $conn->prepare("SELECT l.* FROM labels l JOIN card_labels cl ON l.id = cl.label_id WHERE cl.card_id = ? ORDER BY l.name, l.color");
    $stmt->bind_param('i', $cardId);
    $stmt->execute();
    $cardLabels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'action' => $action,
        'message' => "Label $action",
        'card_labels' => $cardLabels
    ]);

} catch (Exception $e) {
    error_log('Error in label/toggle.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
