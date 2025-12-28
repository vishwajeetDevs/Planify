<?php
/**
 * Delete Checklist API
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
$checklistId = filter_var($data['checklist_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$checklistId) {
    echo json_encode(['success' => false, 'message' => 'Checklist ID required']);
    exit;
}

global $conn;

try {
    $stmt = $conn->prepare("SELECT l.board_id FROM checklists ch JOIN cards c ON ch.card_id = c.id JOIN lists l ON c.list_id = l.id WHERE ch.id = ?");
    $stmt->bind_param('i', $checklistId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result || !hasAccessToBoard($conn, $_SESSION['user_id'], $result['board_id'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM checklists WHERE id = ?");
    $stmt->bind_param('i', $checklistId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Checklist deleted']);
    } else {
        throw new Exception('Failed to delete checklist');
    }

} catch (Exception $e) {
    error_log('Error in checklist/delete.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete checklist']);
}
