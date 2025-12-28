<?php
/**
 * Checklist Item API
 */
error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/NotificationHelper.php';

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

global $conn;

try {
    switch ($action) {
        case 'create':
            $checklistId = filter_var($data['checklist_id'] ?? 0, FILTER_VALIDATE_INT);
            $title = trim($data['title'] ?? '');
            
            if (!$checklistId || !$title) {
                echo json_encode(['success' => false, 'message' => 'Checklist ID and title required']);
                exit;
            }
            
            $stmt = $conn->prepare("SELECT l.board_id FROM checklists ch JOIN cards c ON ch.card_id = c.id JOIN lists l ON c.list_id = l.id WHERE ch.id = ?");
            $stmt->bind_param('i', $checklistId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if (!$result || !hasAccessToBoard($conn, $_SESSION['user_id'], $result['board_id'])) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
            
            $stmt = $conn->prepare("SELECT COALESCE(MAX(position), 0) + 1 as pos FROM checklist_items WHERE checklist_id = ?");
            $stmt->bind_param('i', $checklistId);
            $stmt->execute();
            $pos = $stmt->get_result()->fetch_assoc()['pos'];
            
            $stmt = $conn->prepare("INSERT INTO checklist_items (checklist_id, title, position) VALUES (?, ?, ?)");
            $stmt->bind_param('isi', $checklistId, $title, $pos);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Item added',
                    'item' => [
                        'id' => $conn->insert_id,
                        'checklist_id' => $checklistId,
                        'title' => $title,
                        'is_completed' => false,
                        'position' => $pos
                    ]
                ]);
            } else {
                throw new Exception('Failed to add item');
            }
            break;
            
        case 'toggle':
            $itemId = filter_var($data['item_id'] ?? 0, FILTER_VALIDATE_INT);
            
            if (!$itemId) {
                echo json_encode(['success' => false, 'message' => 'Item ID required']);
                exit;
            }
            
            $stmt = $conn->prepare("SELECT ci.*, ch.card_id, l.board_id FROM checklist_items ci JOIN checklists ch ON ci.checklist_id = ch.id JOIN cards c ON ch.card_id = c.id JOIN lists l ON c.list_id = l.id WHERE ci.id = ?");
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            
            if (!$item || !hasAccessToBoard($conn, $_SESSION['user_id'], $item['board_id'])) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
            
            $newStatus = !$item['is_completed'];
            $completedAt = $newStatus ? date('Y-m-d H:i:s') : null;
            $completedBy = $newStatus ? $_SESSION['user_id'] : null;
            
            $stmt = $conn->prepare("UPDATE checklist_items SET is_completed = ?, completed_at = ?, completed_by = ? WHERE id = ?");
            $stmt->bind_param('isii', $newStatus, $completedAt, $completedBy, $itemId);
            
            if ($stmt->execute()) {
                // Notify assignees about checklist item change
                $notificationHelper = new NotificationHelper($conn);
                $notificationHelper->notifyChecklistUpdate($item['card_id'], $_SESSION['user_id'], $item['title'], $newStatus);
                
                echo json_encode([
                    'success' => true,
                    'is_completed' => $newStatus,
                    'message' => $newStatus ? 'Item completed' : 'Item uncompleted'
                ]);
            } else {
                throw new Exception('Failed to toggle item');
            }
            break;
            
        case 'delete':
            $itemId = filter_var($data['item_id'] ?? 0, FILTER_VALIDATE_INT);
            
            if (!$itemId) {
                echo json_encode(['success' => false, 'message' => 'Item ID required']);
                exit;
            }
            
            $stmt = $conn->prepare("SELECT l.board_id FROM checklist_items ci JOIN checklists ch ON ci.checklist_id = ch.id JOIN cards c ON ch.card_id = c.id JOIN lists l ON c.list_id = l.id WHERE ci.id = ?");
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if (!$result || !hasAccessToBoard($conn, $_SESSION['user_id'], $result['board_id'])) {
                echo json_encode(['success' => false, 'message' => 'Access denied']);
                exit;
            }
            
            $stmt = $conn->prepare("DELETE FROM checklist_items WHERE id = ?");
            $stmt->bind_param('i', $itemId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Item deleted']);
            } else {
                throw new Exception('Failed to delete item');
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log('Error in checklist/item.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
