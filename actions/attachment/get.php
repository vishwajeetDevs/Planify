<?php
/**
 * Get Attachments API
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

$cardId = filter_input(INPUT_GET, 'card_id', FILTER_VALIDATE_INT);

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
    
    $stmt = $conn->prepare("SELECT a.*, u.name as uploader_name FROM attachments a JOIN users u ON a.user_id = u.id WHERE a.card_id = ? ORDER BY a.created_at DESC");
    $stmt->bind_param('i', $cardId);
    $stmt->execute();
    $attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($attachments as &$att) {
        $att['id'] = (int)$att['id'];
        $att['is_image'] = strpos($att['mime_type'] ?? '', 'image/') === 0;
        $bytes = (int)$att['file_size'];
        if ($bytes >= 1048576) $att['file_size_formatted'] = round($bytes / 1048576, 1) . ' MB';
        else if ($bytes >= 1024) $att['file_size_formatted'] = round($bytes / 1024, 1) . ' KB';
        else $att['file_size_formatted'] = $bytes . ' bytes';
    }
    
    echo json_encode(['success' => true, 'attachments' => $attachments]);

} catch (Exception $e) {
    error_log('Error in attachment/get.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
