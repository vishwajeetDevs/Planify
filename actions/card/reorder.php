<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/NotificationHelper.php';
require_once '../../helpers/IdEncrypt.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method'], 400);
}

// Validate CSRF token
validateCSRFToken();

$cardId = intval($_POST['card_id'] ?? 0);
$newListId = intval($_POST['list_id'] ?? 0);
$newPosition = intval($_POST['position'] ?? 0);
$oldListId = intval($_POST['old_list_id'] ?? 0);
$userId = $_SESSION['user_id'];

if ($cardId <= 0 || $newListId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Invalid parameters'], 400);
}

try {
    // Get board ID and board name
    $stmt = $conn->prepare("
        SELECT l.board_id, b.name as board_name
        FROM lists l
        JOIN boards b ON l.board_id = b.id
        WHERE l.id = ?
    ");
    $stmt->bind_param("i", $newListId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(['success' => false, 'message' => 'List not found'], 404);
    }
    
    $list = $result->fetch_assoc();
    $boardId = $list['board_id'];
    $boardName = $list['board_name'];
    
    // Check permission
    if (!canEditBoard($conn, $userId, $boardId)) {
        jsonResponse(['success' => false, 'message' => 'Permission denied'], 403);
    }
    
    // Get card title for activity log
    $stmt = $conn->prepare("SELECT title FROM cards WHERE id = ?");
    $stmt->bind_param("i", $cardId);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    
    // Get old list name BEFORE updating (needed for email notification)
    $oldListName = '';
    if ($oldListId > 0 && $oldListId != $newListId) {
        $stmt = $conn->prepare("SELECT title FROM lists WHERE id = ?");
        $stmt->bind_param("i", $oldListId);
        $stmt->execute();
        $oldListResult = $stmt->get_result()->fetch_assoc();
        $oldListName = $oldListResult ? $oldListResult['title'] : '';
    }
    
    // Update card position and list
    $stmt = $conn->prepare("UPDATE cards SET list_id = ?, position = ? WHERE id = ?");
    $stmt->bind_param("iii", $newListId, $newPosition, $cardId);
    
    if ($stmt->execute()) {
        // Reorder other cards in the new list
        $conn->query("SET @pos = -1");
        $stmt = $conn->prepare("
            UPDATE cards 
            SET position = (@pos := @pos + 1)
            WHERE list_id = ? 
            ORDER BY position ASC, id ASC
        ");
        $stmt->bind_param("i", $newListId);
        $stmt->execute();
        
        // If moved to different list, reorder old list and send notifications
        if ($oldListId != $newListId && $oldListId > 0) {
            $conn->query("SET @pos = -1");
            $stmt = $conn->prepare("
                UPDATE cards 
                SET position = (@pos := @pos + 1)
                WHERE list_id = ? 
                ORDER BY position ASC, id ASC
            ");
            $stmt->bind_param("i", $oldListId);
            $stmt->execute();
            
            // Get new list name
            $stmt = $conn->prepare("SELECT title FROM lists WHERE id = ?");
            $stmt->bind_param("i", $newListId);
            $stmt->execute();
            $newListName = $stmt->get_result()->fetch_assoc()['title'];
            
            // Log activity with both list names
            logActivity($conn, $boardId, $userId, 'card_moved', 
                "moved card \"{$card['title']}\" from \"$oldListName\" to \"$newListName\"", $cardId);
            
            // Send in-app notifications to assigned members
            $notificationHelper = new NotificationHelper($conn);
            $notificationHelper->notifyTaskMoved($cardId, $userId, $oldListName, $newListName);
            
            // Send email notifications to assigned members (async-friendly)
            sendTaskMovedNotifications($conn, $cardId, $userId, $card['title'], $oldListName, $newListName, $boardName, $boardId);
        }
        
        jsonResponse(['success' => true, 'message' => 'Card position updated']);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update card position'], 500);
    }
} catch (Exception $e) {
    secureErrorResponse($e, 'Card reorder');
}

/**
 * Send email notifications to task assignees when task is moved
 * This function is called after the main response to avoid UI delay
 */
function sendTaskMovedNotifications($conn, $cardId, $actorId, $taskTitle, $oldListName, $newListName, $boardName, $boardId) {
    try {
        // Check if card_assignees table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'card_assignees'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return; // No assignees table, skip notifications
        }
        
        // Get all assignees for this card (excluding deleted users)
        $stmt = $conn->prepare("
            SELECT u.id, u.name, u.email 
            FROM card_assignees ca 
            JOIN users u ON ca.user_id = u.id 
            WHERE ca.card_id = ? 
            AND u.email IS NOT NULL 
            AND u.email != ''
        ");
        if (!$stmt) {
            error_log("Failed to prepare assignees query: " . $conn->error);
            return;
        }
        $stmt->bind_param("i", $cardId);
        $stmt->execute();
        $assignees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // If no assignees (other than possibly the actor), skip
        if (empty($assignees)) {
            return;
        }
        
        // Get actor name
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param("i", $actorId);
        $stmt->execute();
        $actorResult = $stmt->get_result()->fetch_assoc();
        $actorName = $actorResult ? $actorResult['name'] : 'Someone';
        $stmt->close();
        
        // Build task URL with encrypted ID (optional - for "View Task" button in email)
        $basePath = defined('BASE_PATH') ? BASE_PATH : '';
        $taskUrl = (defined('APP_URL') ? APP_URL : '') . "/public/board.php?ref=" . encryptId($boardId);
        
        // Include MailHelper and send notifications
        require_once dirname(__DIR__) . '/../src/MailHelper.php';
        
        MailHelper::sendTaskMovedNotifications(
            $assignees,
            $actorId,
            $taskTitle,
            $oldListName,
            $newListName,
            $actorName,
            $boardName,
            $taskUrl
        );
        
    } catch (Exception $e) {
        // Log error but don't fail the main request
        error_log("Task moved notification error: " . $e->getMessage());
    }
}
?>