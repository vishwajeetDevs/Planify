<?php
/**
 * Get Labels API
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

$boardId = filter_input(INPUT_GET, 'board_id', FILTER_VALIDATE_INT);
$cardId = filter_input(INPUT_GET, 'card_id', FILTER_VALIDATE_INT);

if (!$boardId) {
    echo json_encode(['success' => false, 'message' => 'Board ID required']);
    exit;
}

global $conn;

try {
    if (!hasAccessToBoard($conn, $_SESSION['user_id'], $boardId)) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM labels WHERE board_id = ? ORDER BY name, color");
    $stmt->bind_param('i', $boardId);
    $stmt->execute();
    $labels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $cardLabelIds = [];
    if ($cardId) {
        $stmt = $conn->prepare("SELECT label_id FROM card_labels WHERE card_id = ?");
        $stmt->bind_param('i', $cardId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $cardLabelIds[] = (int)$row['label_id'];
        }
    }

    foreach ($labels as &$label) {
        $label['id'] = (int)$label['id'];
        $label['board_id'] = (int)$label['board_id'];
        $label['selected'] = in_array($label['id'], $cardLabelIds);
    }

    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'card_label_ids' => $cardLabelIds
    ]);

} catch (Exception $e) {
    error_log('Error in label/get.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
