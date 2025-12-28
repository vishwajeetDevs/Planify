<?php
/**
 * Card Assignees API
 */
header('Content-Type: application/json; charset=utf-8');

require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/NotificationHelper.php';
require_once '../../src/MailHelper.php';
require_once '../../helpers/IdEncrypt.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $cardId = isset($_GET['card_id']) ? (int)$_GET['card_id'] : 0;
        
        if (!$cardId) {
            echo json_encode(['success' => false, 'message' => 'Card ID required']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT l.board_id FROM cards c JOIN lists l ON c.list_id = l.id WHERE c.id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        $stmt->bind_param('i', $cardId);
        $stmt->execute();
        $card = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$card || !hasAccessToBoard($conn, $_SESSION['user_id'], $card['board_id'])) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        // Get assignees (with error handling for missing table)
        $assignees = [];
        $tableCheck = $conn->query("SHOW TABLES LIKE 'card_assignees'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.avatar, ca.assigned_at FROM card_assignees ca JOIN users u ON ca.user_id = u.id WHERE ca.card_id = ? ORDER BY ca.assigned_at");
            if ($stmt) {
                $stmt->bind_param('i', $cardId);
                $stmt->execute();
                $assignees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            }
        }
        
        // Get board members
        $members = [];
        $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.avatar, bm.role FROM board_members bm JOIN users u ON bm.user_id = u.id WHERE bm.board_id = ? ORDER BY u.name");
        if ($stmt) {
            $stmt->bind_param('i', $card['board_id']);
            $stmt->execute();
            $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
        
        $assignedIds = array_column($assignees, 'id');
        foreach ($members as &$member) {
            $member['id'] = (int)$member['id'];
            $member['assigned'] = in_array($member['id'], $assignedIds);
        }
        
        echo json_encode(['success' => true, 'assignees' => $assignees, 'members' => $members]);
        
    } else if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate CSRF token
        $csrfToken = $data['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($_SESSION['csrf_token']) || empty($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page and try again.']);
            exit;
        }
        
        $action = $data['action'] ?? 'toggle';
        $cardId = isset($data['card_id']) ? (int)$data['card_id'] : 0;
        $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;
        
        if (!$cardId || !$userId) {
            echo json_encode(['success' => false, 'message' => 'Card ID and User ID required']);
            exit;
        }
        
        $stmt = $conn->prepare("SELECT l.board_id FROM cards c JOIN lists l ON c.list_id = l.id WHERE c.id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        $stmt->bind_param('i', $cardId);
        $stmt->execute();
        $card = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Must have edit permission to assign/unassign users
        if (!$card || !canEditBoard($conn, $_SESSION['user_id'], $card['board_id'])) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to modify assignments']);
            exit;
        }
        
        // Check if user is board member
        $stmt = $conn->prepare("SELECT id FROM board_members WHERE board_id = ? AND user_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        $stmt->bind_param('ii', $card['board_id'], $userId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            echo json_encode(['success' => false, 'message' => 'User is not a board member']);
            exit;
        }
        $stmt->close();
        
        // Check existing assignment
        $stmt = $conn->prepare("SELECT id FROM card_assignees WHERE card_id = ? AND user_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        $stmt->bind_param('ii', $cardId, $userId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $resultAction = 'no_change';
        if ($action === 'toggle') {
            if ($existing) {
                $stmt = $conn->prepare("DELETE FROM card_assignees WHERE card_id = ? AND user_id = ?");
                if ($stmt) {
                    $stmt->bind_param('ii', $cardId, $userId);
                    $stmt->execute();
                    $stmt->close();
                    $resultAction = 'removed';
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO card_assignees (card_id, user_id, assigned_by) VALUES (?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('iii', $cardId, $userId, $_SESSION['user_id']);
                    $stmt->execute();
                    $stmt->close();
                    $resultAction = 'added';
                    
                    // Send notification and email to the assigned user (if not self-assigning)
                    if ($userId !== $_SESSION['user_id']) {
                        // Create in-app notification
                        $notificationHelper = new NotificationHelper($conn);
                        $notificationHelper->createAssignmentNotification($userId, $_SESSION['user_id'], $cardId, $card['board_id']);
                        
                        // Send email notification
                        try {
                            // Get task details
                            $taskStmt = $conn->prepare("
                                SELECT c.title, c.due_date, l.title as list_name, b.name as board_name, b.id as board_id
                                FROM cards c 
                                JOIN lists l ON c.list_id = l.id 
                                JOIN boards b ON l.board_id = b.id 
                                WHERE c.id = ?
                            ");
                            $taskStmt->bind_param('i', $cardId);
                            $taskStmt->execute();
                            $taskDetails = $taskStmt->get_result()->fetch_assoc();
                            $taskStmt->close();
                            
                            // Get assigned user details
                            $userStmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
                            $userStmt->bind_param('i', $userId);
                            $userStmt->execute();
                            $assignedUser = $userStmt->get_result()->fetch_assoc();
                            $userStmt->close();
                            
                            // Get assigner (current user) name
                            $assignerStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                            $assignerStmt->bind_param('i', $_SESSION['user_id']);
                            $assignerStmt->execute();
                            $assigner = $assignerStmt->get_result()->fetch_assoc();
                            $assignerStmt->close();
                            
                            if ($taskDetails && $assignedUser && $assigner) {
                                // Build task URL with encrypted ID
                                $taskUrl = (defined('APP_URL') ? APP_URL : BASE_URL) . '/board.php?ref=' . encryptId($taskDetails['board_id']) . '&card=' . $cardId;
                                
                                // Format due date if exists
                                $dueDate = '';
                                if (!empty($taskDetails['due_date'])) {
                                    $dueDate = date('F j, Y', strtotime($taskDetails['due_date']));
                                }
                                
                                // Send email
                                MailHelper::sendTaskAssignedEmail(
                                    $assignedUser['email'],
                                    $assignedUser['name'],
                                    $taskDetails['title'],
                                    $assigner['name'],
                                    $taskDetails['board_name'],
                                    $taskDetails['list_name'],
                                    $taskUrl,
                                    $dueDate
                                );
                            }
                        } catch (Exception $e) {
                            // Log error but don't fail the assignment
                            error_log("Failed to send task assignment email: " . $e->getMessage());
                        }
                    }
                }
            }
        }
        
        // Get updated assignees
        $assignees = [];
        $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.avatar, ca.assigned_at FROM card_assignees ca JOIN users u ON ca.user_id = u.id WHERE ca.card_id = ? ORDER BY ca.assigned_at");
        if ($stmt) {
            $stmt->bind_param('i', $cardId);
            $stmt->execute();
            $assignees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
        
        echo json_encode(['success' => true, 'action' => $resultAction, 'assignees' => $assignees]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
