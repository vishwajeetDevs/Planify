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
        
        $assignees = [];
        $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.avatar, ca.assigned_at FROM card_assignees ca JOIN users u ON ca.user_id = u.id WHERE ca.card_id = ? ORDER BY ca.assigned_at");
        if ($stmt) {
            $stmt->bind_param('i', $cardId);
            $stmt->execute();
            $assignees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
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
        
        if (!$card || !canManageBoard($conn, $_SESSION['user_id'], $card['board_id'])) {
            echo json_encode(['success' => false, 'message' => 'Only Admins can assign or unassign members on tasks']);
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
                }
            }
        }
        
        $assignees = [];
        $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.avatar, ca.assigned_at FROM card_assignees ca JOIN users u ON ca.user_id = u.id WHERE ca.card_id = ? ORDER BY ca.assigned_at");
        if ($stmt) {
            $stmt->bind_param('i', $cardId);
            $stmt->execute();
            $assignees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        if ($resultAction === 'added' || $resultAction === 'removed') {
            $subjectName = 'a member';
            $taskTitle = 'a task';
            $metaStmt = $conn->prepare('SELECT u.name AS user_name, c.title AS card_title FROM users u, cards c WHERE u.id = ? AND c.id = ?');
            if ($metaStmt) {
                $metaStmt->bind_param('ii', $userId, $cardId);
                $metaStmt->execute();
                $meta = $metaStmt->get_result()->fetch_assoc();
                $metaStmt->close();
                if (!empty($meta['user_name'])) {
                    $subjectName = $meta['user_name'];
                }
                if (!empty($meta['card_title'])) {
                    $taskTitle = $meta['card_title'];
                }
            }
            $assignAction = $resultAction === 'added' ? 'card_assigned' : 'card_unassigned';
            $assignVerb = $resultAction === 'added' ? 'assigned' : 'unassigned';
            logActivity(
                $conn,
                (int) $card['board_id'],
                (int) $_SESSION['user_id'],
                $assignAction,
                $assignVerb . ' ' . $subjectName . ' on "' . $taskTitle . '"',
                $cardId
            );
        }

        $notifyUserId = $userId;
        $actorId = (int) $_SESSION['user_id'];
        $boardId = (int) $card['board_id'];
        $shouldNotifyAssigned = $resultAction === 'added' && $notifyUserId !== $actorId;
        $shouldNotifyUnassigned = $resultAction === 'removed' && $notifyUserId !== $actorId;

        planify_finish_json([
            'success' => true,
            'action' => $resultAction,
            'assignees' => $assignees
        ]);

        if ($shouldNotifyAssigned || $shouldNotifyUnassigned) {
            try {
                $taskStmt = $conn->prepare("
                    SELECT c.title, c.due_date, c.list_id, l.title as list_name, b.name as board_name, b.id as board_id,
                           assignee.name as assignee_name, assignee.email as assignee_email,
                           actor.name as actor_name
                    FROM cards c
                    JOIN lists l ON c.list_id = l.id
                    JOIN boards b ON l.board_id = b.id
                    JOIN users assignee ON assignee.id = ?
                    JOIN users actor ON actor.id = ?
                    WHERE c.id = ?
                ");
                $taskStmt->bind_param('iii', $notifyUserId, $actorId, $cardId);
                $taskStmt->execute();
                $taskDetails = $taskStmt->get_result()->fetch_assoc();
                $taskStmt->close();

                if (!$taskDetails || empty($taskDetails['assignee_email'])) {
                    exit;
                }

                $taskUrl = taskPageUrl(
                    (int) $taskDetails['board_id'],
                    $cardId,
                    isset($taskDetails['list_id']) ? (int) $taskDetails['list_id'] : null
                );

                if ($shouldNotifyAssigned) {
                    $notificationHelper = new NotificationHelper($conn);
                    $notificationHelper->createAssignmentNotification($notifyUserId, $actorId, $cardId, $boardId);

                    $dueDate = !empty($taskDetails['due_date']) ? date('F j, Y', strtotime($taskDetails['due_date'])) : '';
                    MailHelper::sendTaskAssignedEmail(
                        $taskDetails['assignee_email'],
                        $taskDetails['assignee_name'],
                        $taskDetails['title'],
                        $taskDetails['actor_name'],
                        $taskDetails['board_name'],
                        $taskDetails['list_name'],
                        $taskUrl,
                        $dueDate
                    );
                }

                if ($shouldNotifyUnassigned) {
                    MailHelper::sendTaskUnassignedEmail(
                        $taskDetails['assignee_email'],
                        $taskDetails['assignee_name'],
                        $taskDetails['title'],
                        $taskDetails['actor_name'],
                        $taskDetails['board_name'],
                        $taskUrl
                    );
                }
            } catch (Exception $e) {
                error_log("Failed to send task assignment email: " . $e->getMessage());
            }
        }
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid method']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

function planify_finish_json(array $payload): void {
    $json = json_encode($payload);
    if ($json === false) {
        $json = '{"success":false,"message":"Server error"}';
    }
    ignore_user_abort(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Content-Length: ' . strlen($json));
    header('Connection: close');
    echo $json;
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}
