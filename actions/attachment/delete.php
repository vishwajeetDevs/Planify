<?php
/**
 * Delete Attachment API
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
$attachmentId = filter_var($data['attachment_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$attachmentId) {
    echo json_encode(['success' => false, 'message' => 'Attachment ID required']);
    exit;
}

global $conn;

try {
    $stmt = $conn->prepare("SELECT a.*, l.board_id FROM attachments a JOIN cards c ON a.card_id = c.id JOIN lists l ON c.list_id = l.id WHERE a.id = ?");
    $stmt->bind_param('i', $attachmentId);
    $stmt->execute();
    $attachment = $stmt->get_result()->fetch_assoc();
    
    if (!$attachment || !hasAccessToBoard($conn, $_SESSION['user_id'], $attachment['board_id'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    $filePath = '../../uploads/attachments/' . $attachment['filename'];
    if (file_exists($filePath)) unlink($filePath);
    
    $stmt = $conn->prepare("DELETE FROM attachments WHERE id = ?");
    $stmt->bind_param('i', $attachmentId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Attachment deleted']);
    } else {
        throw new Exception('Failed to delete attachment');
    }

} catch (Exception $e) {
    error_log('Error in attachment/delete.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to delete attachment']);
}
