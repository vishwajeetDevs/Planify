<?php
/**
 * Delete Label API
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
$labelId = filter_var($data['label_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$labelId) {
    echo json_encode(['success' => false, 'message' => 'Label ID required']);
    exit;
}

global $conn;

try {
    $stmt = $conn->prepare("SELECT board_id FROM labels WHERE id = ?");
    $stmt->bind_param('i', $labelId);
    $stmt->execute();
    $label = $stmt->get_result()->fetch_assoc();
    
    if (!$label || !hasAccessToBoard($conn, $_SESSION['user_id'], $label['board_id'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM labels WHERE id = ?");
    $stmt->bind_param('i', $labelId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Label deleted']);
    } else {
        throw new Exception('Failed to delete label');
    }

} catch (Exception $e) {
    error_log('Error in label/delete.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete label']);
}
